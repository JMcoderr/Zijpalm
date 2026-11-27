<?php

use App\CharityRequestStatus;
use App\Mail\CharityRequestReviewedMail;
use App\Models\CharityAdmin;
use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use App\Models\CharityPaymentBatch;
use App\Models\CharityRequest;
use App\Services\CharitySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    config(['services.charity_magic_links.allowed_domains' => ['example.test']]);
    Mail::fake();

    $event = CharityEvent::create([
        'title' => 'Support event',
    ]);

    $this->eventCost = CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '125.50',
        'start_date' => '2026-01-01 00:00:00',
    ]);

    $this->member = CharityMember::create([
        'email' => 'private-recipient@example.test',
        'full_name' => 'Example Recipient',
        'dob' => '1985-04-12',
    ]);

    $this->request = CharityRequest::create([
        'requester_email' => 'employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Private Account',
        'iban' => 'NL91ABNA0417164300',
    ]);
});

function charityReviewSession(string $email = 'admin@example.test'): array
{
    return [
        'charity_magic' => [
            'email' => $email,
            'expires_at' => now()->addHour()->timestamp,
        ],
    ];
}

function charitySuperadminPaymentSession(?string $executionDate = null): array
{
    return [
        ...charityReviewSession('super@example.test'),
        CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => Crypt::encrypt([
            'name' => 'Zijpalm Vereniging',
            'iban' => 'NL91ABNA0417164300',
            'execution_date' => $executionDate ?? now()->addDays(3)->toDateString(),
        ]),
    ];
}

it('requires an active charity admin to open the review list', function () {
    $this->get(route('charity.requests.review'))
        ->assertRedirect(route('charity.login'));

    $this->withSession(charityReviewSession())
        ->get(route('charity.requests.review'))
        ->assertForbidden();

    Mail::assertNothingSent();
});

it('requires a current superadmin to open the payment overview', function () {
    $this->get(route('charity.requests.payment-overview'))
        ->assertRedirect(route('charity.login'));

    $this->post(route('charity.requests.payment-details'))
        ->assertRedirect(route('charity.login'));

    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->get(route('charity.requests.payment-overview'))
        ->assertForbidden();

    $this->withSession(charityReviewSession())
        ->post(route('charity.requests.payment-details'))
        ->assertForbidden();
});

it('shows only pending requests and leaves private member and bank details off the page', function () {
    $this->member->delete();

    $acceptedRequest = CharityRequest::create([
        'requester_email' => 'another-employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Another Private Account',
        'iban' => 'GB82WEST12345698765432',
    ]);
    $acceptedRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->get(route('charity.requests.review'))
        ->assertOk()
        ->assertSee('Aanvraag #'.$this->request->id)
        ->assertSee('Support event')
        ->assertSee('employee@example.test')
        ->assertSee('Example Recipient')
        ->assertSee('125,50')
        ->assertDontSee('Aanvraag #'.$acceptedRequest->id)
        ->assertDontSee('private-recipient@example.test')
        ->assertDontSee('1985-04-12')
        ->assertDontSee('Private Account')
        ->assertDontSee('NL91ABNA0417164300')
        ->assertDontSee('Another Private Account')
        ->assertDontSee('GB82WEST12345698765432');
});

it('shows the review link only to an active charity admin', function () {
    $this->withSession(charityReviewSession('employee@example.test'))
        ->get(route('charity.login'))
        ->assertOk()
        ->assertDontSee(route('charity.requests.review'));

    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->get(route('charity.login'))
        ->assertOk()
        ->assertSee(route('charity.requests.review'))
        ->assertDontSee(route('charity.requests.payment-overview'));

    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.login'))
        ->assertOk()
        ->assertSee(route('charity.requests.review'))
        ->assertSee(route('charity.requests.payment-overview'));
});

it('shows only accepted payment details on a private uncached superadmin page', function () {
    $acceptedRequest = CharityRequest::create([
        'requester_email' => 'employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Approved Payee',
        'iban' => 'GB82WEST12345698765432',
    ]);
    $acceptedRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $secondAcceptedRequest = CharityRequest::create([
        'requester_email' => 'third-employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Second Approved Payee',
        'iban' => 'BE68539007547034',
    ]);
    $secondAcceptedRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $exportedRequest = CharityRequest::create([
        'requester_email' => 'another-employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Already Exported Payee',
        'iban' => 'DE89370400440532013000',
    ]);
    $exportedRequest->forceFill(['status' => CharityRequestStatus::Exported])->save();

    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $response = $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.requests.payment-overview'))
        ->assertOk();

    $cacheControl = (string) $response->headers->get('Cache-Control');
    $cacheDirectives = array_map('trim', explode(',', strtolower($cacheControl)));

    expect($cacheDirectives)->toContain('no-store')->toContain('private');

    $response
        ->assertSee('Aanvraag #'.$acceptedRequest->id)
        ->assertSee('Support event')
        ->assertSee('Example Recipient')
        ->assertSee('Approved Payee')
        ->assertSee('GB82WEST12345698765432')
        ->assertSee('Second Approved Payee')
        ->assertSee('Voorbereiding SEPA-export')
        ->assertSee(route('charity.requests.payment-details'))
        ->assertSeeText('Aantal goedgekeurd: 2')
        ->assertSeeText('Ontvangergegevens en bedrag geldig: 2')
        ->assertSeeText('Ontvangergegevens of bedrag onvolledig of ongeldig: 0')
        ->assertSee('251,00')
        ->assertSee('125,50')
        ->assertDontSee('Aanvraag #'.$this->request->id)
        ->assertDontSee('employee@example.test')
        ->assertDontSee('private-recipient@example.test')
        ->assertDontSee('Private Account')
        ->assertDontSee('NL91ABNA0417164300')
        ->assertDontSee('Already Exported Payee')
        ->assertDontSee('DE89370400440532013000');

    expect($acceptedRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($secondAcceptedRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($exportedRequest->fresh()->status)->toBe(CharityRequestStatus::Exported);
});

it('flags accepted requests with incomplete payment details before export', function () {
    $this->request->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $missingIbanRequest = CharityRequest::create([
        'requester_email' => 'missing-iban@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Missing IBAN',
        'iban' => null,
    ]);
    $missingIbanRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $missingNameRequest = CharityRequest::create([
        'requester_email' => 'missing-name@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => null,
        'iban' => 'BE68539007547034',
    ]);
    $missingNameRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $blankNameRequest = CharityRequest::create([
        'requester_email' => 'blank-name@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => '   ',
        'iban' => 'BE68539007547034',
    ]);
    $blankNameRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $overlongNameRequest = CharityRequest::create([
        'requester_email' => 'overlong-name@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => str_repeat('A', 71),
        'iban' => 'BE68539007547034',
    ]);
    $overlongNameRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $invalidIbanRequest = CharityRequest::create([
        'requester_email' => 'invalid-iban@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Invalid IBAN',
        'iban' => 'NL00ABNA0417164300',
    ]);
    $invalidIbanRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $zeroAmountCost = CharityEventCost::create([
        'charity_event_id' => $this->eventCost->charity_event_id,
        'amount' => '0.00',
        'start_date' => '2026-01-01 00:00:00',
    ]);
    $zeroAmountRequest = CharityRequest::create([
        'requester_email' => 'zero-amount@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $zeroAmountCost->id,
        'account_name' => 'Zero Amount Payee',
        'iban' => 'BE68539007547034',
    ]);
    $zeroAmountRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $negativeAmountCost = CharityEventCost::create([
        'charity_event_id' => $this->eventCost->charity_event_id,
        'amount' => '-1.00',
        'start_date' => '2026-01-01 00:00:00',
    ]);
    $negativeAmountRequest = CharityRequest::create([
        'requester_email' => 'negative-amount@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $negativeAmountCost->id,
        'account_name' => 'Negative Amount Payee',
        'iban' => 'BE68539007547034',
    ]);
    $negativeAmountRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $response = $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.requests.payment-overview'))
        ->assertOk()
        ->assertSeeText('Aantal goedgekeurd: 8')
        ->assertSeeText('Ontvangergegevens en bedrag geldig: 1')
        ->assertSeeText('Ontvangergegevens of bedrag onvolledig of ongeldig: 7')
        ->assertSeeText('Controleer of de rekeningnaam is ingevuld en maximaal 70 tekens heeft')
        ->assertSee('IBAN: Niet ingevuld');

    expect(substr_count($response->getContent(), 'Gegevens compleet; meegenomen in het totaal.'))->toBe(1)
        ->and(substr_count($response->getContent(), 'Gegevens onvolledig; niet meegenomen in het totaal.'))->toBe(7);

    expect($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($missingIbanRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($missingNameRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($blankNameRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($overlongNameRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($invalidIbanRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($zeroAmountRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($negativeAmountRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted);
});

it('totals only accepted requests with complete positive payment details', function () {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    foreach ([
        ['amount' => '11.25', 'account_name' => 'First Payee', 'iban' => 'BE68539007547034'],
        ['amount' => '20.30', 'account_name' => 'Second Payee', 'iban' => 'GB82WEST12345698765432'],
        ['amount' => '90.00', 'account_name' => 'Invalid IBAN Payee', 'iban' => 'NL00ABNA0417164300'],
    ] as $payment) {
        $eventCost = CharityEventCost::create([
            'charity_event_id' => $this->eventCost->charity_event_id,
            'amount' => $payment['amount'],
            'start_date' => '2026-01-01 00:00:00',
        ]);

        $request = CharityRequest::create([
            'requester_email' => 'payment@example.test',
            'charity_member_id' => $this->member->id,
            'charity_event_cost_id' => $eventCost->id,
            'account_name' => $payment['account_name'],
            'iban' => $payment['iban'],
        ]);
        $request->forceFill(['status' => CharityRequestStatus::Accepted])->save();
    }

    $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.requests.payment-overview'))
        ->assertOk()
        ->assertSeeText('Aantal goedgekeurd: 3')
        ->assertSeeText('Ontvangergegevens en bedrag geldig: 2')
        ->assertSeeText('Ontvangergegevens of bedrag onvolledig of ongeldig: 1')
        ->assertSeeText('Totaal van complete betalingen:')
        ->assertSee('31,55');
});

it('stores validated debtor details encrypted in the superadmin session', function () {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $iban = 'NL91ABNA0417164300';
    $executionDate = now()->addDays(3)->toDateString();

    $this->withSession(charityReviewSession('super@example.test'))
        ->post(route('charity.requests.payment-details'), [
            'debtor_name' => 'Zijpalm Vereniging',
            'debtor_iban' => 'nl91 abna 0417 1643 00',
            'execution_date' => $executionDate,
        ])
        ->assertRedirect(route('charity.requests.payment-overview'))
        ->assertSessionHas('status', 'Betalergegevens en uitvoeringsdatum zijn tijdelijk opgeslagen.')
        ->assertSessionHas(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY, function (string $encryptedDetails) use ($iban, $executionDate): bool {
            $details = Crypt::decrypt($encryptedDetails);

            return is_array($details)
                && $details['name'] === 'Zijpalm Vereniging'
                && $details['iban'] === $iban
                && $details['execution_date'] === $executionDate
                && ! str_contains($encryptedDetails, 'Zijpalm Vereniging')
                && ! str_contains($encryptedDetails, $iban);
        });

    $this->withSession([
        ...charityReviewSession('super@example.test'),
        CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => Crypt::encrypt([
            'name' => 'Zijpalm Vereniging',
            'iban' => $iban,
            'execution_date' => $executionDate,
        ]),
    ])
        ->get(route('charity.requests.payment-overview'))
        ->assertOk()
        ->assertSee('De betalergegevens zijn tijdelijk voor deze sessie opgeslagen.')
        ->assertDontSee($iban);
});

it('clears previous debtor details and only keeps a valid execution date on errors', function (bool $validDate) {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();
    $executionDate = now()->addDays($validDate ? 1 : -1)->toDateString();

    $response = $this->from(route('charity.requests.payment-overview'))
        ->withSession([
            ...charityReviewSession('super@example.test'),
            CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => Crypt::encrypt([
                'name' => 'Previous Payer',
                'iban' => 'NL91ABNA0417164300',
            ]),
        ])
        ->post(route('charity.requests.payment-details'), [
            'debtor_name' => 'Example Payer',
            'debtor_iban' => 'NL00ABNA0417164300',
            'execution_date' => $executionDate,
        ])
        ->assertRedirect(route('charity.requests.payment-overview'))
        ->assertSessionHasErrors('debtor_iban')
        ->assertSessionMissing(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY)
        ->assertSessionMissing('_old_input.debtor_name')
        ->assertSessionMissing('_old_input.debtor_iban');

    if ($validDate) {
        $response->assertSessionHas('_old_input', ['execution_date' => $executionDate]);
    } else {
        $response->assertSessionHasErrors('execution_date')
            ->assertSessionMissing('_old_input.execution_date');
    }

    $this->get(route('charity.requests.payment-overview'))
        ->assertOk()
        ->assertDontSee('Example Payer');
})->with([
    'future date' => true,
    'past date' => false,
]);

it('creates an encrypted export batch once and allows the same XML to be downloaded again', function () {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();
    $this->request->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $secondRequest = CharityRequest::create([
        'requester_email' => 'second-employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Second Export Recipient',
        'iban' => 'BE68539007547034',
    ]);
    $secondRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $response = $this->withSession(charitySuperadminPaymentSession())
        ->post(route('charity.requests.payment-export'))
        ->assertOk()
        ->assertDownload()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSessionMissing(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);

    $xml = $response->streamedContent();
    $document = new DOMDocument;
    expect($document->loadXML($xml, LIBXML_NONET))->toBeTrue();

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('sepa', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

    $batch = CharityPaymentBatch::query()->sole();
    $storedBatch = DB::table('charity_payment_batches')->where('id', $batch->id)->first();

    expect($batch->message_id)->toStartWith('ZP')
        ->and($batch->debtor_name)->toBe('Zijpalm Vereniging')
        ->and($batch->debtor_iban)->toBe('NL91ABNA0417164300')
        ->and($batch->transaction_count)->toBe(2)
        ->and($batch->total_amount_cents)->toBe(25100)
        ->and($batch->status)->toBe(CharityRequestStatus::Exported->value)
        ->and($batch->xml_content)->toBe($xml)
        ->and($storedBatch->debtor_name)->not->toBe('Zijpalm Vereniging')
        ->and($storedBatch->debtor_iban)->not->toBe('NL91ABNA0417164300')
        ->and($storedBatch->xml_content)->not->toContain('NL91ABNA0417164300')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:GrpHdr/sepa:NbOfTxs')->item(0)?->textContent)
        ->toBe('2')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:GrpHdr/sepa:CtrlSum')->item(0)?->textContent)
        ->toBe('251.00')
        ->and($this->request->fresh()->status)->toBe(CharityRequestStatus::Exported)
        ->and($this->request->fresh()->charity_payment_batch_id)->toBe($batch->id)
        ->and($this->request->fresh()->paid_at)->toBeNull()
        ->and($secondRequest->fresh()->status)->toBe(CharityRequestStatus::Exported)
        ->and($secondRequest->fresh()->charity_payment_batch_id)->toBe($batch->id);

    $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.requests.payment-batches.download', $batch))
        ->assertOk()
        ->assertDownload('sepa-batch-'.$batch->id.'.xml')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertStreamedContent($xml);

    $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.requests.payment-overview'))
        ->assertOk()
        ->assertSeeText('Batch #'.$batch->id.' - 2 betalingen')
        ->assertSee(route('charity.requests.payment-batches.download', $batch));

    $this->from(route('charity.requests.payment-overview'))
        ->withSession(charitySuperadminPaymentSession())
        ->post(route('charity.requests.payment-export'))
        ->assertRedirect(route('charity.requests.payment-overview'))
        ->assertSessionHasErrors('export');

    expect(CharityPaymentBatch::query()->count())->toBe(1);
});

it('blocks exporting accepted requests when any payment details are incomplete', function () {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();
    $this->request->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $incompleteRequest = CharityRequest::create([
        'requester_email' => 'incomplete@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => str_repeat('A', 71),
        'iban' => 'BE68539007547034',
    ]);
    $incompleteRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $this->from(route('charity.requests.payment-overview'))
        ->withSession(charitySuperadminPaymentSession())
        ->post(route('charity.requests.payment-export'))
        ->assertRedirect(route('charity.requests.payment-overview'))
        ->assertSessionHasErrors('export');

    expect(CharityPaymentBatch::query()->count())->toBe(0)
        ->and($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($incompleteRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($this->request->fresh()->charity_payment_batch_id)->toBeNull()
        ->and($incompleteRequest->fresh()->charity_payment_batch_id)->toBeNull();
});

it('rejects expired payer details and keeps batch downloads superadmin-only', function () {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();
    $this->request->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $this->from(route('charity.requests.payment-overview'))
        ->withSession(charitySuperadminPaymentSession(now()->subDay()->toDateString()))
        ->post(route('charity.requests.payment-export'))
        ->assertRedirect(route('charity.requests.payment-overview'))
        ->assertSessionHasErrors('export')
        ->assertSessionMissing(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);

    $batch = CharityPaymentBatch::query()->create([
        'message_id' => 'ZPACCESSCHECK',
        'debtor_name' => 'Zijpalm Vereniging',
        'debtor_iban' => 'NL91ABNA0417164300',
        'execution_date' => now()->addDays(3)->toDateString(),
        'transaction_count' => 1,
        'total_amount_cents' => 12550,
        'xml_content' => '<xml/>',
        'status' => CharityRequestStatus::Exported->value,
        'exported_at' => now(),
    ]);

    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->get(route('charity.requests.payment-batches.download', $batch))
        ->assertForbidden();

    expect($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and(CharityPaymentBatch::query()->count())->toBe(1);
});

it('allows an admin to accept a pending request', function () {
    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Accepted->value,
        ])
        ->assertRedirect(route('charity.requests.review'))
        ->assertSessionHas('status', 'Aanvraag goedgekeurd.');

    expect($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($this->request->fresh()->iban)->toBe('NL91ABNA0417164300')
        ->and($this->request->fresh()->account_name)->toBe('Private Account');

    Mail::assertSent(CharityRequestReviewedMail::class, function (CharityRequestReviewedMail $mail): bool {
        if (! $mail->hasTo('employee@example.test') || ! $mail->approved) {
            return false;
        }

        $body = $mail->render();

        return str_contains($body, 'is goedgekeurd')
            && ! str_contains($body, 'NL91ABNA0417164300')
            && ! str_contains($body, 'Private Account');
    });
    Mail::assertSentCount(1);
});

it('shows that the decision was saved when the review email fails', function () {
    CharityAdmin::create(['employee' => 'admin@example.test']);
    Mail::shouldReceive('to->send')->once()->andThrow(new TransportException('Mail server unavailable'));

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Accepted->value,
        ])
        ->assertRedirect(route('charity.requests.review'))
        ->assertSessionHas('status', 'Aanvraag goedgekeurd.')
        ->assertSessionHasErrors(['notification' => 'De beoordeling is opgeslagen, maar de e-mail kon niet worden verstuurd.']);

    $this->get(route('charity.requests.review'))
        ->assertOk()
        ->assertSeeText('De beoordeling is opgeslagen, maar de e-mail kon niet worden verstuurd.');

    expect($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted);
});

it('rejects a pending request and clears its bank details', function () {
    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Rejected->value,
            'reason' => 'De aanvraag voldoet niet aan de voorwaarden.',
        ])
        ->assertRedirect(route('charity.requests.review'))
        ->assertSessionHas('status', 'Aanvraag afgewezen.');

    $reviewedRequest = $this->request->fresh();

    expect($reviewedRequest->status)->toBe(CharityRequestStatus::Rejected)
        ->and($reviewedRequest->reason)->toBe('De aanvraag voldoet niet aan de voorwaarden.')
        ->and($reviewedRequest->iban)->toBeNull()
        ->and($reviewedRequest->account_name)->toBeNull();

    Mail::assertSent(CharityRequestReviewedMail::class, function (CharityRequestReviewedMail $mail): bool {
        if (! $mail->hasTo('employee@example.test') || $mail->approved) {
            return false;
        }

        $body = $mail->render();

        return str_contains($body, 'De aanvraag voldoet niet aan de voorwaarden.')
            && ! str_contains($body, 'NL91ABNA0417164300')
            && ! str_contains($body, 'Private Account');
    });
    Mail::assertSentCount(1);
});

it('does not allow unsupported or repeated review decisions', function () {
    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Paid->value,
        ])
        ->assertSessionHasErrors('status');

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Accepted->value,
        ])
        ->assertRedirect(route('charity.requests.review'));

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Rejected->value,
        ])
        ->assertSessionHasErrors('status');

    expect($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted);
    Mail::assertSentCount(1);
});
