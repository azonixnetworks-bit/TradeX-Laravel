<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

trait CreatesKycTestFiles
{
    protected function kycPng(
        string $name = 'kyc-document.png'
    ): UploadedFile {
        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl+X8sAAAAASUVORK5CYII=',
            true
        );

        if (! is_string($content)) {
            throw new \RuntimeException(
                'Unable to create KYC PNG test fixture.'
            );
        }

        return UploadedFile::fake()
            ->createWithContent(
                $name,
                $content
            );
    }

    protected function kycPdf(
        string $name = 'kyc-document.pdf'
    ): UploadedFile {
        return UploadedFile::fake()
            ->createWithContent(
                $name,
                "%PDF-1.4\n"
                ."1 0 obj\n"
                ."<< /Type /Catalog >>\n"
                ."endobj\n"
                ."trailer\n"
                ."<< /Root 1 0 R >>\n"
                ."%%EOF\n"
            );
    }

    protected function kycText(
        string $name = 'kyc-document.txt'
    ): UploadedFile {
        return UploadedFile::fake()
            ->createWithContent(
                $name,
                'plain text is not a valid KYC identity image'
            );
    }

    protected function sriLankaNicPayload(
        string $identityNumber = '199512345678'
    ): array {
        return [
            'document_type' =>
                'NATIONAL_ID',

            'legal_name' =>
                'TradeX KYC User',

            'date_of_birth' =>
                '1995-05-15',

            'identity_number' =>
                $identityNumber,

            'address' =>
                '10 Test Street, Colombo',

            'identity_front' =>
                $this->kycPng(
                    'nic-front.png'
                ),

            'identity_back' =>
                $this->kycPng(
                    'nic-back.png'
                ),

            'selfie' =>
                $this->kycPng(
                    'selfie.png'
                ),
        ];
    }

    protected function sriLankaPassportPayload(
        string $identityNumber = 'N1234567'
    ): array {
        return [
            'document_type' =>
                'PASSPORT',

            'legal_name' =>
                'TradeX Passport User',

            'date_of_birth' =>
                '1994-04-14',

            'identity_number' =>
                $identityNumber,

            'address' =>
                '20 Test Street, Kandy',

            'identity_front' =>
                $this->kycPdf(
                    'passport.pdf'
                ),

            'selfie' =>
                $this->kycPng(
                    'passport-selfie.png'
                ),
        ];
    }
}
