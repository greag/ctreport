<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExporter
{
    public function createExcel(array $data, string $fileName): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->addReportInformationSheet($spreadsheet, $data);
        $this->addPersonalInformationSheet($spreadsheet, $data);
        $this->addIdAndContactSheets($spreadsheet, $data);
        $this->addEmploymentSheet($spreadsheet, $data);
        $this->addAccountsSheets($spreadsheet, $data);
        $this->addEnquiriesSheet($spreadsheet, $data);
        $this->addAdditionalInformationSheet($spreadsheet, $data);

        $tempPath = storage_path('app/results/' . $fileName . '_Extracted_Info.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return $tempPath;
    }

    private function addReportInformationSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $reportInfo = $data['ReportInformation'] ?? [];
        $rows = [
            ['Score', 'Report Date', 'Control Number'],
            [
                $reportInfo['Score'] ?? 'N/A',
                $reportInfo['ReportDate'] ?? 'N/A',
                $reportInfo['ControlNumber'] ?? 'N/A',
            ],
        ];
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Report Information');
        $sheet->fromArray($rows, null, 'A1');
    }

    private function addPersonalInformationSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $personal = $data['PersonalInformation'] ?? null;
        if (!$personal) {
            return;
        }
        $rows = [
            ['Name', 'Date of Birth', 'Gender'],
            [
                $personal['Name'] ?? 'N/A',
                $personal['DateOfBirth'] ?? 'N/A',
                $personal['Gender'] ?? 'N/A',
            ],
        ];
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Personal Information');
        $sheet->fromArray($rows, null, 'A1');
    }

    private function addIdAndContactSheets(Spreadsheet $spreadsheet, array $data): void
    {
        $idAndContact = $data['IDAndContactInfo'] ?? null;
        if (!$idAndContact) {
            return;
        }

        $identifications = $idAndContact['Identifications'] ?? [];
        if (!empty($identifications)) {
            $rows = [['Sequence', 'Identification Type', 'ID Number', 'Issue Date', 'Expiry Date']];
            foreach ($identifications as $id) {
                $rows[] = [
                    $id['Sequence'] ?? 'N/A',
                    $id['IdentificationType'] ?? 'N/A',
                    $id['IdNumber'] ?? 'N/A',
                    $id['IssueDate'] ?? 'N/A',
                    $id['ExpiryDate'] ?? 'N/A',
                ];
            }
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Identification Details');
            $sheet->fromArray($rows, null, 'A1');
        }

        $addresses = $idAndContact['ContactInformation']['Addresses'] ?? [];
        if (!empty($addresses)) {
            $rows = [['Sequence', 'Address', 'Type', 'Residence Code', 'Date Reported']];
            foreach ($addresses as $addr) {
                $rows[] = [
                    $addr['Sequence'] ?? 'N/A',
                    $addr['Address'] ?? 'N/A',
                    $addr['Type'] ?? 'N/A',
                    $addr['ResidenceCode'] ?? 'N/A',
                    $addr['DateReported'] ?? 'N/A',
                ];
            }
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Addresses');
            $sheet->fromArray($rows, null, 'A1');
        }

        $telephones = $idAndContact['ContactInformation']['Telephones'] ?? [];
        if (!empty($telephones)) {
            $rows = [['Sequence', 'Telephone Number Type', 'Telephone Number', 'Telephone Extension']];
            foreach ($telephones as $tel) {
                $rows[] = [
                    $tel['Sequence'] ?? 'N/A',
                    $tel['Type'] ?? 'N/A',
                    $tel['Number'] ?? 'N/A',
                    $tel['Extension'] ?? 'N/A',
                ];
            }
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Telephones');
            $sheet->fromArray($rows, null, 'A1');
        }

        $emails = $idAndContact['ContactInformation']['Emails'] ?? [];
        if (!empty($emails)) {
            $rows = [['Sequence', 'Email Address']];
            foreach ($emails as $email) {
                $rows[] = [
                    $email['Sequence'] ?? 'N/A',
                    $email['EmailAddress'] ?? 'N/A',
                ];
            }
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Emails');
            $sheet->fromArray($rows, null, 'A1');
        }
    }

    private function addEmploymentSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $employment = $data['EmploymentInformation'] ?? null;
        if (!$employment) {
            return;
        }
        $rows = [
            ['Account Type', 'Date Reported', 'Occupation', 'Income', 'Monthly / Annual Income Indicator', 'Net / Gross Income Indicator'],
            [
                $employment['AccountType'] ?? 'N/A',
                $employment['DateReported'] ?? 'N/A',
                $employment['Occupation'] ?? 'N/A',
                $employment['Income'] ?? 'N/A',
                $employment['MonthlyAnnualIncomeIndicator'] ?? 'N/A',
                $employment['NetGrossIncomeIndicator'] ?? 'N/A',
            ],
        ];
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Employment');
        $sheet->fromArray($rows, null, 'A1');
    }

    private function addAccountsSheets(Spreadsheet $spreadsheet, array $data): void
    {
        $accounts = $data['Accounts'] ?? [];
        if (empty($accounts)) {
            return;
        }

        $accountRows = [[
            'Sequence', 'Member Name', 'Account Type', 'Account Number', 'Ownership type', 'Credit Limit',
            'High Credit', 'Sanctioned Amount', 'Current Balance', 'Cash Limit', 'Amount Overdue', 'Rate of Interest',
            'Repayment Tenure', 'EMI Amount', 'Payment Frequency', 'Actual Payment Amount', 'Date Opened', 'Date Closed',
            'Last Payment Date', 'Date Reported And Certified', 'Value of Collateral', 'Type of Collateral',
            'Suit - Filed / Willful Default', 'Credit Facility Status', 'Written-off Amount (Total)',
            'Written-off Amount (Principal)', 'Settlement Amount', 'Payment Start Date', 'Payment End Date',
        ]];

        foreach ($accounts as $acc) {
            $accountRows[] = [
                $acc['Sequence'] ?? 'N/A',
                $acc['MemberName'] ?? 'N/A',
                $acc['AccountType'] ?? 'N/A',
                $acc['AccountNumber'] ?? 'N/A',
                $acc['OwnershipType'] ?? 'N/A',
                $acc['CreditLimit'] ?? 'N/A',
                $acc['HighCredit'] ?? 'N/A',
                $acc['SanctionedAmount'] ?? 'N/A',
                $acc['CurrentBalance'] ?? 'N/A',
                $acc['CashLimit'] ?? 'N/A',
                $acc['AmountOverdue'] ?? 'N/A',
                $acc['RateOfInterest'] ?? 'N/A',
                $acc['RepaymentTenure'] ?? 'N/A',
                $acc['EmiAmount'] ?? 'N/A',
                $acc['PaymentFrequency'] ?? 'N/A',
                $acc['ActualPaymentAmount'] ?? 'N/A',
                $acc['DateOpened'] ?? 'N/A',
                $acc['DateClosed'] ?? 'N/A',
                $acc['LastPaymentDate'] ?? 'N/A',
                $acc['DateReportedAndCertified'] ?? 'N/A',
                $acc['ValueOfCollateral'] ?? 'N/A',
                $acc['TypeOfCollateral'] ?? 'N/A',
                $acc['SuitFiledWillfulDefault'] ?? 'N/A',
                $acc['CreditFacilityStatus'] ?? 'N/A',
                $acc['WrittenOffAmountTotal'] ?? 'N/A',
                $acc['WrittenOffAmountPrincipal'] ?? 'N/A',
                $acc['SettlementAmount'] ?? 'N/A',
                $acc['PaymentStartDate'] ?? 'N/A',
                $acc['PaymentEndDate'] ?? 'N/A',
            ];
        }

        $accountSheet = $spreadsheet->createSheet();
        $accountSheet->setTitle('Accounts');
        $accountSheet->fromArray($accountRows, null, 'A1');

        $paymentRows = [[
            'Account Sequence', 'Member Name', 'Account Number', 'Payment Start Date', 'Payment End Date',
            'Month', 'Year', 'DPD',
        ]];

        foreach ($accounts as $acc) {
            $history = $acc['PaymentHistory'] ?? [];
            if (empty($history)) {
                $paymentRows[] = [
                    $acc['Sequence'] ?? 'N/A',
                    $acc['MemberName'] ?? 'N/A',
                    $acc['AccountNumber'] ?? 'N/A',
                    $acc['PaymentStartDate'] ?? 'N/A',
                    $acc['PaymentEndDate'] ?? 'N/A',
                    'N/A',
                    'N/A',
                    'N/A',
                ];
                continue;
            }
            foreach ($history as $entry) {
                $paymentRows[] = [
                    $acc['Sequence'] ?? 'N/A',
                    $acc['MemberName'] ?? 'N/A',
                    $acc['AccountNumber'] ?? 'N/A',
                    $acc['PaymentStartDate'] ?? 'N/A',
                    $acc['PaymentEndDate'] ?? 'N/A',
                    $entry['Month'] ?? 'N/A',
                    $entry['Year'] ?? 'N/A',
                    $entry['DaysPastDue'] ?? 'N/A',
                ];
            }
        }

        $paymentSheet = $spreadsheet->createSheet();
        $paymentSheet->setTitle('Payment Status');
        $paymentSheet->fromArray($paymentRows, null, 'A1');
    }

    private function addEnquiriesSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $enquiries = $data['Enquiries'] ?? [];
        if (empty($enquiries)) {
            return;
        }
        $rows = [['Sequence', 'Member Name', 'Date of Enquiry', 'Enquiry Purpose']];
        foreach ($enquiries as $enq) {
            $rows[] = [
                $enq['Sequence'] ?? 'N/A',
                $enq['MemberName'] ?? 'N/A',
                $enq['DateOfEnquiry'] ?? 'N/A',
                $enq['EnquiryPurpose'] ?? 'N/A',
            ];
        }
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Enquiries');
        $sheet->fromArray($rows, null, 'A1');
    }

    private function addAdditionalInformationSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $additional = $data['AdditionalInformation'] ?? [];
        if (empty($additional)) {
            return;
        }
        $rows = [['Sequence', 'Label', 'Value']];
        foreach ($additional as $info) {
            $rows[] = [
                $info['Sequence'] ?? 'N/A',
                $info['Label'] ?? 'N/A',
                $info['Value'] ?? 'N/A',
            ];
        }
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Additional Information');
        $sheet->fromArray($rows, null, 'A1');
    }
}
