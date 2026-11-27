<?php

use App\Services\CharitySepaXmlBuilder;

it('builds a SEPA credit transfer document with the correct totals and escaped names', function () {
    $builder = new CharitySepaXmlBuilder;
    $xml = $builder->build(
        [
            [
                'request_id' => 12,
                'account_name' => 'A & B <C>',
                'iban' => 'be68 5390 0754 7034',
                'amount_cents' => 1125,
            ],
            [
                'request_id' => 13,
                'account_name' => str_repeat('B', 70),
                'iban' => 'GB82WEST12345698765432',
                'amount_cents' => 2030,
            ],
        ],
        'Zijpalm & Co',
        'nl91 abna 0417 1643 00',
        new DateTimeImmutable('2026-12-01'),
        new DateTimeImmutable('2026-11-27T15:00:00+01:00'),
    );

    $document = new DOMDocument;
    expect($document->loadXML($xml, LIBXML_NONET))->toBeTrue();

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('sepa', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

    expect($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:GrpHdr/sepa:NbOfTxs')->item(0)?->textContent)
        ->toBe('2')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:GrpHdr/sepa:CreDtTm')->item(0)?->textContent)
        ->toBe('2026-11-27T14:00:00Z')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:GrpHdr/sepa:CtrlSum')->item(0)?->textContent)
        ->toBe('31.55')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:PmtInf/sepa:ReqdExctnDt/sepa:Dt')->item(0)?->textContent)
        ->toBe('2026-12-01')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:PmtInf/sepa:DbtrAcct/sepa:Id/sepa:IBAN')->item(0)?->textContent)
        ->toBe('NL91ABNA0417164300')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:PmtInf/sepa:CdtTrfTxInf[1]/sepa:Cdtr/sepa:Nm')->item(0)?->textContent)
        ->toBe('A & B <C>')
        ->and(mb_strlen($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:PmtInf/sepa:CdtTrfTxInf[2]/sepa:Cdtr/sepa:Nm')->item(0)?->textContent ?? '', 'UTF-8'))
        ->toBe(70)
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:PmtInf/sepa:CdtTrfTxInf[1]/sepa:Amt/sepa:InstdAmt')->item(0)?->textContent)
        ->toBe('11.25')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:PmtInf/sepa:CdtTrfTxInf[1]/sepa:PmtId/sepa:EndToEndId')->item(0)?->textContent)
        ->toBe('ZP-12')
        ->and($xpath->query('/sepa:Document/sepa:CstmrCdtTrfInitn/sepa:PmtInf/sepa:DbtrAgt/sepa:FinInstnId/sepa:BICFI')->item(0)?->textContent)
        ->toBe('NOTPROVIDED');
});

it('rejects SEPA XML input that is incomplete, invalid or duplicated', function (array $payments, string $debtorName, string $debtorIban) {
    (new CharitySepaXmlBuilder)->build(
        $payments,
        $debtorName,
        $debtorIban,
        new DateTimeImmutable('2026-12-01'),
        new DateTimeImmutable('2026-11-27T14:00:00Z'),
    );
})->with([
    'no payments' => [[], 'Payer Name', 'NL91ABNA0417164300'],
    'blank debtor name' => [[['request_id' => 1, 'account_name' => 'Recipient', 'iban' => 'BE68539007547034', 'amount_cents' => 100]], ' ', 'NL91ABNA0417164300'],
    'debtor name longer than the SEPA limit' => [[['request_id' => 1, 'account_name' => 'Recipient', 'iban' => 'BE68539007547034', 'amount_cents' => 100]], str_repeat('A', 71), 'NL91ABNA0417164300'],
    'invalid debtor IBAN' => [[['request_id' => 1, 'account_name' => 'Recipient', 'iban' => 'BE68539007547034', 'amount_cents' => 100]], 'Payer Name', 'NL00ABNA0417164300'],
    'invalid recipient IBAN' => [[['request_id' => 1, 'account_name' => 'Recipient', 'iban' => 'NL00ABNA0417164300', 'amount_cents' => 100]], 'Payer Name', 'NL91ABNA0417164300'],
    'empty recipient name' => [[['request_id' => 1, 'account_name' => ' ', 'iban' => 'BE68539007547034', 'amount_cents' => 100]], 'Payer Name', 'NL91ABNA0417164300'],
    'name longer than the SEPA limit' => [[['request_id' => 1, 'account_name' => str_repeat('A', 71), 'iban' => 'BE68539007547034', 'amount_cents' => 100]], 'Payer Name', 'NL91ABNA0417164300'],
    'recipient name contains an invalid XML character' => [[['request_id' => 1, 'account_name' => "Bad\x01Name", 'iban' => 'BE68539007547034', 'amount_cents' => 100]], 'Payer Name', 'NL91ABNA0417164300'],
    'zero amount' => [[['request_id' => 1, 'account_name' => 'Recipient', 'iban' => 'BE68539007547034', 'amount_cents' => 0]], 'Payer Name', 'NL91ABNA0417164300'],
    'repeated request' => [
        [
            ['request_id' => 1, 'account_name' => 'Recipient', 'iban' => 'BE68539007547034', 'amount_cents' => 100],
            ['request_id' => 1, 'account_name' => 'Recipient', 'iban' => 'BE68539007547034', 'amount_cents' => 100],
        ],
        'Payer Name',
        'NL91ABNA0417164300',
    ],
])->throws(InvalidArgumentException::class);
