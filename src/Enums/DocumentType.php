<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Enums;

enum DocumentType: string
{
    case MyKad = '1';
    case MyTentera = '2';
    case Passport = '3';
    case MyKid = '4';
    case MyPr = '5';
    case MyKas = '6';
    case PhPrc = '7';
    case PhDrivingLicense = '8';
    case PhSss = '9';
    case PhUmid = '10';
    case PhVoterId = '11';
    case PhNationalId = '12';
    case PhPostalId = '13';
    case CambodiaId = '14';
    case IndonesiaEktp = '15';

    public function label(): string
    {
        return match ($this) {
            self::MyKad => 'MyKad',
            self::MyTentera => 'MyTentera',
            self::Passport => 'Passport',
            self::MyKid => 'MyKid',
            self::MyPr => 'MyPR',
            self::MyKas => 'MyKas',
            self::PhPrc => 'PH PRC',
            self::PhDrivingLicense => 'PH Driving License',
            self::PhSss => 'PH SSS',
            self::PhUmid => 'PH UMID',
            self::PhVoterId => 'PH Voter ID',
            self::PhNationalId => 'PH National ID',
            self::PhPostalId => 'PH Postal ID',
            self::CambodiaId => 'Cambodia ID',
            self::IndonesiaEktp => 'Indonesia e-KTP',
        };
    }
}
