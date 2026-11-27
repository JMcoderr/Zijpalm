<?php

use App\CharityRequestStatus;
use App\Mail\CharityRequestReviewedMail;
use App\Models\CharityAdmin;
use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use App\Models\CharityRequest;
use App\Services\CharitySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
        ->assertSeeText('Ontvangergegevens ontbreken of bedrag ongeldig: 0')
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

    $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.requests.payment-overview'))
        ->assertOk()
        ->assertSeeText('Aantal goedgekeurd: 7')
        ->assertSeeText('Ontvangergegevens en bedrag geldig: 1')
        ->assertSeeText('Ontvangergegevens ontbreken of bedrag ongeldig: 6')
        ->assertSeeText('Een ontvangergegeven ontbreekt, een IBAN klopt niet of een bedrag is nul of negatief.')
        ->assertSee('IBAN: Niet ingevuld');

    expect($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($missingIbanRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($missingNameRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($blankNameRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($invalidIbanRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($zeroAmountRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($negativeAmountRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted);
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

it('does not keep previous debtor details or flash an invalid iban', function () {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $this->from(route('charity.requests.payment-overview'))
        ->withSession([
            ...charityReviewSession('super@example.test'),
            CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => Crypt::encrypt([
                'name' => 'Previous Payer',
                'iban' => 'NL91ABNA0417164300',
            ]),
        ])
        ->post(route('charity.requests.payment-details'), [
            'debtor_name' => ' ',
            'debtor_iban' => 'NL00ABNA0417164300',
            'execution_date' => now()->subDay()->toDateString(),
        ])
        ->assertRedirect(route('charity.requests.payment-overview'))
        ->assertSessionHasErrors(['debtor_name', 'debtor_iban', 'execution_date'])
        ->assertSessionMissing(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY)
        ->assertSessionMissing('_old_input.debtor_iban');
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
