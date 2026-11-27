<?php

namespace App\Services;

use App\Rules\ValidIban;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use OverflowException;
use XMLWriter;

class CharitySepaXmlBuilder
{
    private const NAMESPACE = 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09';

    private const MAX_TRANSFER_AMOUNT_CENTS = 9999999999999;

    /**
     * @param  list<array{request_id: int, account_name: string, iban: string, amount_cents: int}>  $payments
     */
    public function build(
        string $messageId,
        array $payments,
        string $debtorName,
        string $debtorIban,
        DateTimeInterface $executionDate,
        DateTimeInterface $createdAt,
    ): string {
        if (! preg_match('/\A[A-Za-z0-9-]{1,35}\z/', $messageId)) {
            throw new InvalidArgumentException('The SEPA message ID must contain between 1 and 35 letters, numbers or hyphens.');
        }

        $debtorName = trim($debtorName);
        $debtorIban = $this->normalizeIban($debtorIban);

        if ($debtorName === ''
            || mb_strlen($debtorName, 'UTF-8') > CharityPaymentReadiness::MAX_SEPA_PARTY_NAME_LENGTH
        ) {
            throw new InvalidArgumentException('The SEPA debtor name must contain between 1 and 70 characters.');
        }

        $this->validateXmlText($debtorName);

        if (! (new ValidIban)->isValid($debtorIban)) {
            throw new InvalidArgumentException('The SEPA debtor IBAN is invalid.');
        }

        if ($executionDate->format('Y-m-d') < today()->toDateString()) {
            throw new InvalidArgumentException('The SEPA execution date cannot be in the past.');
        }

        if ($payments === []) {
            throw new InvalidArgumentException('At least one complete payment is required.');
        }

        $transferCount = count($payments);
        $totalAmountInCents = 0;
        $validatedPayments = [];
        $seenRequestIds = [];
        $ibanRule = new ValidIban;

        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                throw new InvalidArgumentException('Each SEPA payment must contain valid recipient details.');
            }

            $requestId = $payment['request_id'] ?? null;
            $accountName = $payment['account_name'] ?? null;
            $iban = $payment['iban'] ?? null;
            $amountInCents = $payment['amount_cents'] ?? null;

            if (! is_int($requestId) || $requestId < 1 || isset($seenRequestIds[$requestId])) {
                throw new InvalidArgumentException('Each SEPA payment must have a unique request ID.');
            }

            if (! is_string($accountName)) {
                throw new InvalidArgumentException('Each SEPA payment must have a valid recipient name.');
            }

            $accountName = trim($accountName);

            if ($accountName === ''
                || mb_strlen($accountName, 'UTF-8') > CharityPaymentReadiness::MAX_SEPA_PARTY_NAME_LENGTH
            ) {
                throw new InvalidArgumentException('Each SEPA recipient name must contain between 1 and 70 characters.');
            }

            $this->validateXmlText($accountName);

            if (! is_string($iban) || ! $ibanRule->isValid($iban)) {
                throw new InvalidArgumentException('Each SEPA payment must have a valid recipient IBAN.');
            }

            if (! is_int($amountInCents)
                || $amountInCents < 1
                || $amountInCents > self::MAX_TRANSFER_AMOUNT_CENTS
            ) {
                throw new InvalidArgumentException('Each SEPA payment must have a positive supported amount.');
            }

            if ($totalAmountInCents > PHP_INT_MAX - $amountInCents) {
                throw new OverflowException('The total SEPA payment amount is too large.');
            }

            $seenRequestIds[$requestId] = true;
            $totalAmountInCents += $amountInCents;
            $validatedPayments[] = [
                'request_id' => $requestId,
                'account_name' => $accountName,
                'iban' => $this->normalizeIban($iban),
                'amount_cents' => $amountInCents,
            ];
        }

        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElementNS(null, 'Document', self::NAMESPACE);
        $writer->startElement('CstmrCdtTrfInitn');

        $writer->startElement('GrpHdr');
        $writer->writeElement('MsgId', $messageId);
        $createdAtUtc = DateTimeImmutable::createFromInterface($createdAt)
            ->setTimezone(new DateTimeZone('UTC'));
        $writer->writeElement('CreDtTm', $createdAtUtc->format('Y-m-d\TH:i:s\Z'));
        $writer->writeElement('NbOfTxs', (string) $transferCount);
        $writer->writeElement('CtrlSum', $this->formatAmount($totalAmountInCents));
        $writer->startElement('InitgPty');
        $writer->writeElement('Nm', $debtorName);
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('PmtInf');
        $writer->writeElement('PmtInfId', $messageId);
        $writer->writeElement('PmtMtd', 'TRF');
        $writer->writeElement('NbOfTxs', (string) $transferCount);
        $writer->writeElement('CtrlSum', $this->formatAmount($totalAmountInCents));
        $writer->startElement('PmtTpInf');
        $writer->startElement('SvcLvl');
        $writer->writeElement('Cd', 'SEPA');
        $writer->endElement();
        $writer->endElement();
        $writer->startElement('ReqdExctnDt');
        $writer->writeElement('Dt', $executionDate->format('Y-m-d'));
        $writer->endElement();
        $writer->startElement('Dbtr');
        $writer->writeElement('Nm', $debtorName);
        $writer->endElement();
        $writer->startElement('DbtrAcct');
        $writer->startElement('Id');
        $writer->writeElement('IBAN', $debtorIban);
        $writer->endElement();
        $writer->endElement();
        $writer->startElement('DbtrAgt');
        $writer->startElement('FinInstnId');
        // Do not guess a BIC when the bank details only provide an IBAN.
        $writer->writeElement('BICFI', 'NOTPROVIDED');
        $writer->endElement();
        $writer->endElement();
        $writer->writeElement('ChrgBr', 'SLEV');

        foreach ($validatedPayments as $payment) {
            $writer->startElement('CdtTrfTxInf');
            $writer->startElement('PmtId');
            $writer->writeElement('EndToEndId', 'ZP-'.$payment['request_id']);
            $writer->endElement();
            $writer->startElement('Amt');
            $writer->startElement('InstdAmt');
            $writer->writeAttribute('Ccy', 'EUR');
            $writer->text($this->formatAmount($payment['amount_cents']));
            $writer->endElement();
            $writer->endElement();
            $writer->startElement('CdtrAgt');
            $writer->startElement('FinInstnId');
            $writer->writeElement('BICFI', 'NOTPROVIDED');
            $writer->endElement();
            $writer->endElement();
            $writer->startElement('Cdtr');
            $writer->writeElement('Nm', $payment['account_name']);
            $writer->endElement();
            $writer->startElement('CdtrAcct');
            $writer->startElement('Id');
            $writer->writeElement('IBAN', $payment['iban']);
            $writer->endElement();
            $writer->endElement();
            $writer->startElement('RmtInf');
            $writer->writeElement('Ustrd', 'Lief & Leed aanvraag #'.$payment['request_id']);
            $writer->endElement();
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    private function validateXmlText(string $value): void
    {
        $invalidXmlCharacter = '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u';

        if (! mb_check_encoding($value, 'UTF-8') || preg_match($invalidXmlCharacter, $value) !== 0) {
            throw new InvalidArgumentException('SEPA party names must contain valid XML text.');
        }
    }

    private function formatAmount(int $amountInCents): string
    {
        return intdiv($amountInCents, 100).'.'.str_pad((string) ($amountInCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
