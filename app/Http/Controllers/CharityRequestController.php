<?php

namespace App\Http\Controllers;

use App\CharityRequestStatus;
use App\Mail\CharityRequestReviewedMail;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use App\Models\CharityPaymentBatch;
use App\Models\CharityRequest;
use App\Rules\ValidIban;
use App\Services\CharityPaymentReadiness;
use App\Services\CharitySepaXmlBuilder;
use App\Services\CharitySessionService;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CharityRequestController extends Controller
{
    public function index(): View
    {
        // Load only the details needed for review; bank data stays out of this page.
        $requests = CharityRequest::query()
            ->select([
                'id',
                'requester_email',
                'charity_member_id',
                'charity_event_cost_id',
                'status',
                'created_at',
            ])
            ->where('status', CharityRequestStatus::Pending->value)
            ->with([
                'member' => static fn ($query) => $query->withTrashed()->select(['id', 'full_name', 'name']),
                'eventCost:id,charity_event_id,amount',
                'eventCost.event:id,title',
            ])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('charity.requests.index', [
            'requests' => $requests,
        ]);
    }

    public function paymentOverview(Request $request, CharityPaymentReadiness $paymentReadiness): Response
    {
        // Bank details are only loaded for the superadmin-only payment overview.
        $acceptedRequests = CharityRequest::query()
            ->where('status', CharityRequestStatus::Accepted->value);

        $requests = (clone $acceptedRequests)
            ->select([
                'id',
                'account_name',
                'iban',
                'charity_member_id',
                'charity_event_cost_id',
                'created_at',
            ])
            ->with([
                'member' => static fn ($query) => $query->withTrashed()->select(['id', 'full_name', 'name']),
                'eventCost:id,charity_event_id,amount',
                'eventCost.event:id,title',
            ])
            ->orderBy('created_at')
            ->paginate(15);

        $acceptedRequestCount = $requests->total();
        $requestsWithValidRecipientAndAmountCount = 0;
        $paymentReadyTotalInCents = 0;

        // Use the same check in the list and the total so each request's status is clear.
        $requests->getCollection()->each(function (CharityRequest $charityRequest) use ($paymentReadiness): void {
            $charityRequest->setAttribute(
                'payment_details_complete',
                $paymentReadiness->isComplete($charityRequest),
            );
        });

        // Check encrypted IBANs after decryption and load event costs a batch at a time.
        (clone $acceptedRequests)
            ->select(['id', 'account_name', 'iban', 'charity_event_cost_id'])
            ->with('eventCost:id,amount')
            ->chunkById(100, function ($requests) use (
                $paymentReadiness,
                &$requestsWithValidRecipientAndAmountCount,
                &$paymentReadyTotalInCents,
            ): void {
                foreach ($requests as $charityRequest) {
                    if (! $paymentReadiness->isComplete($charityRequest)) {
                        continue;
                    }

                    $requestsWithValidRecipientAndAmountCount++;
                    $paymentReadyTotalInCents += (int) $paymentReadiness->amountToCents($charityRequest->eventCost?->amount);
                }
            });

        $paymentBatches = CharityPaymentBatch::query()
            ->select([
                'id',
                'execution_date',
                'transaction_count',
                'total_amount_cents',
                'status',
                'exported_at',
            ])
            ->orderByDesc('exported_at')
            ->limit(10)
            ->get();

        return response()
            ->view('charity.requests.payment-overview', [
                'requests' => $requests,
                'acceptedRequestCount' => $acceptedRequestCount,
                'requestsWithValidRecipientAndAmountCount' => $requestsWithValidRecipientAndAmountCount,
                'requestsWithIncompletePaymentDetailsCount' => $acceptedRequestCount - $requestsWithValidRecipientAndAmountCount,
                'paymentReadyTotal' => number_format($paymentReadyTotalInCents / 100, 2, ',', '.'),
                'maxSepaPartyNameLength' => CharityPaymentReadiness::MAX_SEPA_PARTY_NAME_LENGTH,
                'paymentBatches' => $paymentBatches,
                'hasPaymentDebtorDetails' => $request->session()->has(
                    CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY
                ),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function storePaymentDebtorDetails(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'debtor_name' => ['required', 'string', 'min:2', 'max:'.CharityPaymentReadiness::MAX_SEPA_PARTY_NAME_LENGTH],
            'debtor_iban' => ['required', 'string', new ValidIban],
            'execution_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [
            'debtor_name.required' => 'Vul de naam van de rekeninghouder in.',
            'debtor_iban.required' => 'Vul het IBAN van de betaler in.',
            'execution_date.required' => 'Kies de gewenste uitvoeringsdatum.',
            'execution_date.date_format' => 'Vul een geldige uitvoeringsdatum in.',
            'execution_date.after_or_equal' => 'De uitvoeringsdatum mag niet in het verleden liggen.',
        ]);

        // A failed update must not leave older payment details available for a later export.
        $request->session()->forget(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);

        if ($validator->fails()) {
            // Only keep the date; old input is not encrypted by this form.
            return back()
                ->withErrors($validator)
                ->withInput($validator->errors()->has('execution_date') ? [] : $request->only('execution_date'));
        }

        $validated = $validator->validated();
        $iban = strtoupper((string) preg_replace('/\s+/', '', $validated['debtor_iban']));

        // Session encryption can be disabled by deployment config, so protect payer details explicitly.
        $request->session()->put(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY, Crypt::encrypt([
            'name' => trim($validated['debtor_name']),
            'iban' => $iban,
            // Keep the requested date with the payer details; the export must not guess it.
            'execution_date' => $validated['execution_date'],
        ]));

        return redirect()
            ->route('charity.requests.payment-overview')
            ->with('status', 'Betalergegevens en uitvoeringsdatum zijn tijdelijk opgeslagen.');
    }

    public function exportPaymentBatch(
        Request $request,
        CharityPaymentReadiness $paymentReadiness,
        CharitySepaXmlBuilder $xmlBuilder,
    ): RedirectResponse|StreamedResponse {
        $encryptedDetails = $request->session()->get(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);

        if (! is_string($encryptedDetails)) {
            return back()->withErrors([
                'export' => 'Vul eerst de betalergegevens en uitvoeringsdatum in.',
            ]);
        }

        try {
            $storedDetails = Crypt::decrypt($encryptedDetails);
        } catch (DecryptException) {
            return $this->clearInvalidPaymentDebtorDetails($request);
        }

        if (! is_array($storedDetails)) {
            return $this->clearInvalidPaymentDebtorDetails($request);
        }

        $debtorValidator = Validator::make([
            'debtor_name' => $storedDetails['name'] ?? null,
            'debtor_iban' => $storedDetails['iban'] ?? null,
            'execution_date' => $storedDetails['execution_date'] ?? null,
        ], [
            'debtor_name' => ['required', 'string', 'min:2', 'max:'.CharityPaymentReadiness::MAX_SEPA_PARTY_NAME_LENGTH],
            'debtor_iban' => ['required', 'string', new ValidIban],
            'execution_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        if ($debtorValidator->fails()) {
            return $this->clearInvalidPaymentDebtorDetails($request);
        }

        $debtorDetails = $debtorValidator->validated();
        $exportResult = DB::transaction(function () use (
            $debtorDetails,
            $paymentReadiness,
            $xmlBuilder,
        ): array {
            // Lock all accepted requests so concurrent export attempts cannot create duplicate batches.
            $acceptedRequests = CharityRequest::query()
                ->where('status', CharityRequestStatus::Accepted->value)
                ->with('eventCost:id,amount')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'account_name', 'iban', 'charity_event_cost_id']);

            if ($acceptedRequests->isEmpty()) {
                return ['error' => 'no_requests'];
            }

            $payments = [];
            $totalAmountInCents = 0;

            foreach ($acceptedRequests as $charityRequest) {
                if (! $paymentReadiness->isComplete($charityRequest)) {
                    return ['error' => 'incomplete'];
                }

                $amountInCents = $paymentReadiness->amountToCents($charityRequest->eventCost?->amount);

                if ($amountInCents === null || $totalAmountInCents > PHP_INT_MAX - $amountInCents) {
                    return ['error' => 'invalid_total'];
                }

                $totalAmountInCents += $amountInCents;
                $payments[] = [
                    'request_id' => (int) $charityRequest->getKey(),
                    'account_name' => $charityRequest->account_name,
                    'iban' => $charityRequest->iban,
                    'amount_cents' => $amountInCents,
                ];
            }

            $messageId = 'ZP'.strtoupper((string) Str::ulid());
            $exportedAt = now();
            $xml = $xmlBuilder->build(
                $messageId,
                $payments,
                $debtorDetails['debtor_name'],
                $debtorDetails['debtor_iban'],
                new DateTimeImmutable($debtorDetails['execution_date']),
                $exportedAt,
            );

            $batch = CharityPaymentBatch::query()->create([
                'message_id' => $messageId,
                'debtor_name' => trim($debtorDetails['debtor_name']),
                'debtor_iban' => strtoupper((string) preg_replace('/\s+/', '', $debtorDetails['debtor_iban'])),
                'execution_date' => $debtorDetails['execution_date'],
                'transaction_count' => count($payments),
                'total_amount_cents' => $totalAmountInCents,
                'xml_content' => $xml,
                'status' => CharityRequestStatus::Exported->value,
                'exported_at' => $exportedAt,
            ]);

            $updatedRequestCount = CharityRequest::query()
                ->whereKey(array_column($payments, 'request_id'))
                ->where('status', CharityRequestStatus::Accepted->value)
                ->update([
                    'charity_payment_batch_id' => $batch->id,
                    'status' => CharityRequestStatus::Exported->value,
                    'updated_at' => $exportedAt,
                ]);

            if ($updatedRequestCount !== count($payments)) {
                throw new \RuntimeException('The SEPA batch could not be applied to all accepted requests.');
            }

            return ['batch' => $batch];
        });

        if (isset($exportResult['error'])) {
            $message = match ($exportResult['error']) {
                'no_requests' => 'Er staan geen goedgekeurde aanvragen klaar voor export.',
                'incomplete' => 'Los eerst de onvolledige goedgekeurde aanvragen op voordat je een export maakt.',
                default => 'Het totaal van de aanvragen is te groot om veilig te exporteren.',
            };

            return back()->withErrors(['export' => $message]);
        }

        $request->session()->forget(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);

        return $this->downloadSepaXml($exportResult['batch']);
    }

    public function downloadPaymentBatch(CharityPaymentBatch $charityPaymentBatch): StreamedResponse
    {
        return $this->downloadSepaXml($charityPaymentBatch);
    }

    private function clearInvalidPaymentDebtorDetails(Request $request): RedirectResponse
    {
        $request->session()->forget(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);

        return redirect()
            ->route('charity.requests.payment-overview')
            ->withErrors(['export' => 'De betalergegevens zijn niet meer geldig. Vul ze opnieuw in.']);
    }

    private function downloadSepaXml(CharityPaymentBatch $batch): StreamedResponse
    {
        $xml = $batch->xml_content;

        abort_unless(is_string($xml) && $xml !== '', 410);

        return response()->streamDownload(
            static function () use ($xml): void {
                echo $xml;
            },
            'sepa-batch-'.$batch->id.'.xml',
            [
                'Cache-Control' => 'no-store, private',
                'Content-Type' => 'application/xml; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function create(): View
    {
        $eventCosts = CharityEventCost::query()
            ->active()
            ->with('event:id,title')
            ->orderBy('charity_event_id')
            ->get(['id', 'charity_event_id'])
            ->map(static fn (CharityEventCost $eventCost): array => [
                'id' => $eventCost->id,
                'title' => $eventCost->event->title,
            ]);

        // Keep contact and milestone details server-side; the page only needs a name and ID.
        $members = CharityMember::query()
            ->availableForRequests()
            ->orderBy('full_name')
            ->orderBy('id')
            ->get(['id', 'full_name', 'name'])
            ->map(static function (CharityMember $member): array {
                $name = trim((string) ($member->full_name ?: $member->name));

                if ($name === '') {
                    $name = 'Lid '.$member->id;
                }

                return [
                    'id' => $member->id,
                    'label' => $name.' (ID '.$member->id.')',
                ];
            });

        return view('charity.requests.create', [
            'eventCosts' => $eventCosts,
            'members' => $members,
            'maxSepaPartyNameLength' => CharityPaymentReadiness::MAX_SEPA_PARTY_NAME_LENGTH,
        ]);
    }

    public function store(Request $request, CharitySessionService $charitySession): RedirectResponse
    {
        // Use the verified session, not an email supplied by the form.
        $requesterEmail = $charitySession->activeEmail($request);

        if ($requesterEmail === null) {
            return redirect()->route('charity.login');
        }

        $member = null;
        $eventCost = null;

        $validator = Validator::make($request->all(), [
            'charity_member_id' => ['required', 'integer'],
            'charity_event_cost_id' => ['required', 'integer'],
            'account_name' => ['required', 'string', 'max:'.CharityPaymentReadiness::MAX_SEPA_PARTY_NAME_LENGTH],
            'iban' => ['required', 'string', new ValidIban],
        ], [
            'charity_member_id.required' => 'Kies een lid.',
            'charity_event_cost_id.required' => 'Kies een actieve gelegenheid.',
            'account_name.required' => 'Vul de naam in zoals die bij de bank bekend is.',
            'account_name.max' => 'De naam mag niet langer zijn dan '
                .CharityPaymentReadiness::MAX_SEPA_PARTY_NAME_LENGTH.' tekens.',
        ]);

        $validator->after(function (ValidatorContract $validator) use (&$member, &$eventCost, $requesterEmail): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->getData();
            $member = CharityMember::query()
                ->availableForRequests()
                ->whereKey($data['charity_member_id'])
                ->first();

            if ($member === null) {
                $validator->errors()->add('charity_member_id', 'Kies een lid dat nog beschikbaar is.');
            }

            $eventCost = CharityEventCost::query()
                ->active()
                ->whereKey($data['charity_event_cost_id'])
                ->first();

            if ($eventCost === null) {
                $validator->errors()->add('charity_event_cost_id', 'Deze gelegenheid is niet meer actief.');
            }

            $memberEmail = $member?->email;

            if (is_string($memberEmail) && strcasecmp(trim($memberEmail), trim($requesterEmail)) === 0) {
                $matchingMembers = CharityMember::query()
                    ->availableForRequests()
                    ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($memberEmail))])
                    ->count();

                if ($matchingMembers === 1) {
                    $validator->errors()->add('charity_member_id', 'Je kunt geen aanvraag voor jezelf indienen.');
                }
            }
        });

        if ($validator->fails()) {
            // Keep bank details out of flashed input; they do not belong in session data.
            return back()
                ->withErrors($validator)
                ->withInput($request->only(['charity_member_id', 'charity_event_cost_id']));
        }

        if ($member === null || $eventCost === null) {
            throw new \LogicException('Validated request choices were not loaded.');
        }

        $validated = $validator->validated();
        $iban = strtoupper((string) preg_replace('/\s+/', '', $validated['iban']));

        CharityRequest::query()->create([
            'requester_email' => $requesterEmail,
            'charity_member_id' => $member->id,
            'charity_event_cost_id' => $eventCost->id,
            'account_name' => trim($validated['account_name']),
            'iban' => $iban,
        ]);

        return redirect()
            ->route('charity.requests.create')
            ->with('status', 'Je aanvraag is opgeslagen en wacht op beoordeling.');
    }

    public function update(Request $request, CharityRequest $charityRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                CharityRequestStatus::Accepted->value,
                CharityRequestStatus::Rejected->value,
            ])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = CharityRequestStatus::from($validated['status']);
        $reason = $validated['reason'] ?? null;

        $reviewed = DB::transaction(function () use ($charityRequest, $status, $reason): bool {
            $lockedRequest = CharityRequest::query()
                ->whereKey($charityRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $lockedRequest->review($status, $reason);
        });

        if (! $reviewed) {
            return back()->withErrors([
                'status' => 'Deze aanvraag is al beoordeeld.',
            ]);
        }

        $response = redirect()
            ->route('charity.requests.review')
            ->with('status', $status === CharityRequestStatus::Accepted
                ? 'Aanvraag goedgekeurd.'
                : 'Aanvraag afgewezen.');

        // The decision is already saved, even if the mail server is unavailable.
        try {
            Mail::to($charityRequest->requester_email)->send(new CharityRequestReviewedMail(
                approved: $status === CharityRequestStatus::Accepted,
                reason: $reason,
            ));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Lief & Leed review notification failed.', [
                'charity_request_id' => $charityRequest->getKey(),
                'error_type' => $exception::class,
            ]);

            $response->withErrors([
                'notification' => 'De beoordeling is opgeslagen, maar de e-mail kon niet worden verstuurd.',
            ]);
        }

        return $response;
    }
}
