<?php
declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;

final class SepaPain008Generator
{
    /**
     * Generate a SEPA Direct Debit initiation XML (pain.008.001.08).
     *
     * Important:
     * - Elements MUST be in the ISO20022 namespace, otherwise many bank uploaders do not recognize the format.
     */
    public function generate(array $settings, array $run, array $items): string
    {
        $val = new ValidationService();

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $ns = 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.08';

        // Root in namespace
        $document = $doc->createElementNS($ns, 'Document');
        $document->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        // schemaLocation is optional and can confuse some uploaders, so we omit it on purpose
        $doc->appendChild($document);

        $initn = $doc->createElementNS($ns, 'CstmrDrctDbtInitn');
        $document->appendChild($initn);

        $ctrlSum = 0.0;
        foreach ($items as $it) {
            $ctrlSum += (float)$it['amount'];
        }

        $msgId = 'DD' . date('YmdHis') . 'R' . (string)$run['id'];
        $pmtInfId = 'PMT' . (string)$run['id'];

        // Group header
        $grpHdr = $doc->createElementNS($ns, 'GrpHdr');
        $initn->appendChild($grpHdr);
        $grpHdr->appendChild($doc->createElementNS($ns, 'MsgId', $msgId));
        $grpHdr->appendChild($doc->createElementNS($ns, 'CreDtTm', date('Y-m-d\TH:i:s')));
        $grpHdr->appendChild($doc->createElementNS($ns, 'NbOfTxs', (string)count($items)));
        $grpHdr->appendChild($doc->createElementNS($ns, 'CtrlSum', $val->money($ctrlSum)));

        $initg = $doc->createElementNS($ns, 'InitgPty');
        $grpHdr->appendChild($initg);
        $initg->appendChild($doc->createElementNS($ns, 'Nm', $val->text((string)($settings['initiating_party_name'] ?: $settings['creditor_name']))));

        // Payment information
        $pmtInf = $doc->createElementNS($ns, 'PmtInf');
        $initn->appendChild($pmtInf);
        $pmtInf->appendChild($doc->createElementNS($ns, 'PmtInfId', $pmtInfId));
        $pmtInf->appendChild($doc->createElementNS($ns, 'PmtMtd', 'DD'));
        $pmtInf->appendChild($doc->createElementNS($ns, 'NbOfTxs', (string)count($items)));
        $pmtInf->appendChild($doc->createElementNS($ns, 'CtrlSum', $val->money($ctrlSum)));

        $pmtTpInf = $doc->createElementNS($ns, 'PmtTpInf');
        $pmtInf->appendChild($pmtTpInf);

        $svcLvl = $doc->createElementNS($ns, 'SvcLvl');
        $pmtTpInf->appendChild($svcLvl);
        $svcLvl->appendChild($doc->createElementNS($ns, 'Cd', 'SEPA'));

        $lcl = $doc->createElementNS($ns, 'LclInstrm');
        $pmtTpInf->appendChild($lcl);
        $lcl->appendChild($doc->createElementNS($ns, 'Cd', (string)($run['scheme_default'] ?: $settings['default_scheme'])));

        // Sequence type (FRST/RCUR/OOFF/FNAL)
        $seq = (string)($run['sequence_type'] ?? 'RCUR');
        $pmtTpInf->appendChild($doc->createElementNS($ns, 'SeqTp', $seq));

        $pmtInf->appendChild($doc->createElementNS($ns, 'ReqdColltnDt', (string)$run['collection_date']));

        // Creditor
        $cdtr = $doc->createElementNS($ns, 'Cdtr');
        $pmtInf->appendChild($cdtr);
        $cdtr->appendChild($doc->createElementNS($ns, 'Nm', $val->text((string)$settings['creditor_name'])));

        // Creditor account
        $cdtrAcct = $doc->createElementNS($ns, 'CdtrAcct');
        $pmtInf->appendChild($cdtrAcct);
        $cdtrAcctId = $doc->createElementNS($ns, 'Id');
        $cdtrAcct->appendChild($cdtrAcctId);
        $cdtrAcctId->appendChild($doc->createElementNS($ns, 'IBAN', $val->iban((string)$settings['creditor_iban'])));

        // Creditor agent (BICFI or NOTPROVIDED)
        $cdtrAgt = $doc->createElementNS($ns, 'CdtrAgt');
        $pmtInf->appendChild($cdtrAgt);
        $cdtrFin = $doc->createElementNS($ns, 'FinInstnId');
        $cdtrAgt->appendChild($cdtrFin);
        $credBic = trim((string)($settings['creditor_bic'] ?? ''));
        if ($credBic !== '') {
            $cdtrFin->appendChild($doc->createElementNS($ns, 'BICFI', $val->bic($credBic)));
        } else {
            $othr = $doc->createElementNS($ns, 'Othr');
            $cdtrFin->appendChild($othr);
            $othr->appendChild($doc->createElementNS($ns, 'Id', 'NOTPROVIDED'));
        }

        $pmtInf->appendChild($doc->createElementNS($ns, 'ChrgBr', 'SLEV'));

        // Creditor Scheme ID
        $cdtrSchmeId = $doc->createElementNS($ns, 'CdtrSchmeId');
        $pmtInf->appendChild($cdtrSchmeId);
        $cdtrSchId = $doc->createElementNS($ns, 'Id');
        $cdtrSchmeId->appendChild($cdtrSchId);
        $prvt = $doc->createElementNS($ns, 'PrvtId');
        $cdtrSchId->appendChild($prvt);
        $othr = $doc->createElementNS($ns, 'Othr');
        $prvt->appendChild($othr);
        $othr->appendChild($doc->createElementNS($ns, 'Id', $val->creditorId((string)$settings['creditor_id'])));
        $schmeNm = $doc->createElementNS($ns, 'SchmeNm');
        $othr->appendChild($schmeNm);
        $schmeNm->appendChild($doc->createElementNS($ns, 'Prtry', 'SEPA'));

        // Transactions
        foreach ($items as $it) {
            $tx = $doc->createElementNS($ns, 'DrctDbtTxInf');
            $pmtInf->appendChild($tx);

            $pmtId = $doc->createElementNS($ns, 'PmtId');
            $tx->appendChild($pmtId);
            $pmtId->appendChild($doc->createElementNS($ns, 'EndToEndId', $val->endToEndId((string)$it['end_to_end_id'])));

            // Amount
            $instdAmt = $doc->createElementNS($ns, 'InstdAmt', $val->money((float)$it['amount']));
            $instdAmt->setAttribute('Ccy', (string)($it['currency'] ?? 'EUR'));
            $tx->appendChild($instdAmt);

            // Direct Debit transaction / mandate info
            $drct = $doc->createElementNS($ns, 'DrctDbtTx');
            $tx->appendChild($drct);
            $mndt = $doc->createElementNS($ns, 'MndtRltdInf');
            $drct->appendChild($mndt);
            $mndt->appendChild($doc->createElementNS($ns, 'MndtId', $val->mandateId((string)$it['mandate_reference'])));
$sigDateRaw = (string)($it['mandate_signature_date'] ?? ($it['mandate_date'] ?? ''));
$sigDateRaw = trim($sigDateRaw);
if ($sigDateRaw === '' || $sigDateRaw === '0000-00-00') {
    $sigDate = date('Y-m-d');
} else {
    // Accept common inputs and normalize to ISODate YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sigDateRaw)) {
        $sigDate = $sigDateRaw;
    } else {
        $ts = strtotime($sigDateRaw);
        $sigDate = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
$mndt->appendChild($doc->createElementNS($ns, 'DtOfSgntr', $sigDate));
            if (!empty($it['amendment_info'])) {
                // Optional if you ever need it later
            }

            // Debtor agent (BICFI or NOTPROVIDED)
            $dbtrAgt = $doc->createElementNS($ns, 'DbtrAgt');
            $tx->appendChild($dbtrAgt);
            $dbtrFin = $doc->createElementNS($ns, 'FinInstnId');
            $dbtrAgt->appendChild($dbtrFin);
            $debBic = trim((string)($it['debtor_bic'] ?? ''));
            if ($debBic !== '') {
                $dbtrFin->appendChild($doc->createElementNS($ns, 'BICFI', $val->bic($debBic)));
            } else {
                $oth = $doc->createElementNS($ns, 'Othr');
                $dbtrFin->appendChild($oth);
                $oth->appendChild($doc->createElementNS($ns, 'Id', 'NOTPROVIDED'));
            }


            // Debtor
            $dbtr = $doc->createElementNS($ns, 'Dbtr');
            $tx->appendChild($dbtr);
            $dbtr->appendChild($doc->createElementNS($ns, 'Nm', $val->text((string)$it['debtor_name'])));

            // Debtor account
            $dbtrAcct = $doc->createElementNS($ns, 'DbtrAcct');
            $tx->appendChild($dbtrAcct);
            $dbtrAcctId = $doc->createElementNS($ns, 'Id');
            $dbtrAcct->appendChild($dbtrAcctId);
            $dbtrAcctId->appendChild($doc->createElementNS($ns, 'IBAN', $val->iban((string)$it['debtor_iban'])));

            // Remittance info (max 140 chars for Ustrd in many bank systems)
            $rmt = $doc->createElementNS($ns, 'RmtInf');
            $tx->appendChild($rmt);
            $rmt->appendChild($doc->createElementNS($ns, 'Ustrd', $val->remittance((string)$it['remittance'])));

        }

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Konnte XML nicht generieren');
        }
        return $xml;
    }
}