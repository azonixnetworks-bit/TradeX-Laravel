<?php

namespace Tests\Unit\Support;

use App\Support\FixedDecimal;
use PHPUnit\Framework\TestCase;

class FixedDecimalTest extends TestCase
{
    public function test_it_adds_subtracts_and_rounds_without_float_math(): void
    {
        $this->assertSame('1234.56789123', FixedDecimal::normalize('1234.56789123'));
        $this->assertSame('1000.00000001', FixedDecimal::add('999.99999999', '0.00000002'));
        $this->assertSame('-1.25000000', FixedDecimal::sub('1.25', '2.5'));
        $this->assertSame('987.65431298', FixedDecimal::multiplyBasisPoints('1234.56789123', 8000));
        $this->assertSame('246.91357825', FixedDecimal::multiplyBasisPoints('1234.56789123', 2000));
    }

    public function test_customer_company_split_can_be_reconciled_exactly(): void
    {
        $eligible = '0.00000003';
        $customer = FixedDecimal::multiplyBasisPoints($eligible, 8000);
        $companyIndependent = FixedDecimal::multiplyBasisPoints($eligible, 2000);
        $adjustment = FixedDecimal::sub(
            $eligible,
            FixedDecimal::add($customer, $companyIndependent)
        );
        $company = FixedDecimal::add($companyIndependent, $adjustment);

        $this->assertSame($eligible, FixedDecimal::add($customer, $company));
    }
}
