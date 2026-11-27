<?php

namespace App\Http\Controllers;

use App\CharityRequestStatus;
use App\Mail\CharityRequestReviewedMail;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use App\Models\CharityRequest;
use App\Rules\ValidIban;
use App\Services\CharitySessionService;
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
use Illuminate\Validation\Rule;
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

    public function paymentOverview(Request $request): Response
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

        // Encrypted IBANs can only be checked for presence in this query.
        $requestsWithRecipientDetailsCount = (clone $acceptedRequests)
            ->whereNotNull('account_name')
            ->whereRaw("TRIM(account_name) <> ''")
            ->whereNotNull('iban')
            ->count();

        $acceptedTotal = (clone $acceptedRequests)
            ->join('charity_event_costs', 'charity_requests.charity_event_cost_id', '=', 'charity_event_costs.id')
            ->sum('charity_event_costs.amount');

        return response()
            ->view('charity.requests.payment-overview', [
                'requests' => $requests,
                'acceptedRequestCount' => $acceptedRequestCount,
                'requestsWithRecipientDetailsCount' => $requestsWithRecipientDetailsCount,
                'requestsMissingRecipientDetailsCount' => $acceptedRequestCount - $requestsWithRecipientDetailsCount,
                'acceptedTotal' => $acceptedTotal,
                'hasPaymentDebtorDetails' => $request->session()->has(
                    CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY
                ),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function storePaymentDebtorDetails(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'debtor_name' => ['required', 'string', 'min:2', 'max:70'],
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
            return back()
                ->withErrors($validator)
                ->withInput($request->except('debtor_iban'));
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
            'account_name' => ['required', 'string', 'max:100'],
            'iban' => ['required', 'string', new ValidIban],
        ], [
            'charity_member_id.required' => 'Kies een lid.',
            'charity_event_cost_id.required' => 'Kies een actieve gelegenheid.',
            'account_name.required' => 'Vul de naam in zoals die bij de bank bekend is.',
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
