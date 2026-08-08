<?php

namespace App\Services\Aggregation\Support;

/**
 * Maps free-text province/city labels onto the project's known Iran provinces.
 * Does not invent cities or provinces when no confident match exists.
 */
class IranGeoNormalizer
{
    /** @var list<string> */
    public const PROVINCES = [
        'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز', 'ایلام', 'بوشهر', 'تهران',
        'چهارمحال و بختیاری', 'خراسان جنوبی', 'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان',
        'سمنان', 'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان', 'کرمان', 'کرمانشاه',
        'کهگیلویه و بویراحمد', 'گلستان', 'گیلان', 'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد',
    ];

    /** @var array<string, string> alias key (normalized) => canonical province */
    protected static array $aliases = [
        'تهران' => 'تهران',
        'استان تهران' => 'تهران',
        'آذربایجان شرقی' => 'آذربایجان شرقی',
        'اذربایجان شرقی' => 'آذربایجان شرقی',
        'آذربایجان غربی' => 'آذربایجان غربی',
        'اذربایجان غربی' => 'آذربایجان غربی',
        'چهار محال و بختیاری' => 'چهارمحال و بختیاری',
        'چهارمحال بختیاری' => 'چهارمحال و بختیاری',
        'كهگيلويه و بويراحمد' => 'کهگیلویه و بویراحمد',
        'کهگیلویه و بویر احمد' => 'کهگیلویه و بویراحمد',
        'سیستان و بلوچستان' => 'سیستان و بلوچستان',
        'سيستان و بلوچستان' => 'سیستان و بلوچستان',
        'خراسان رضوي' => 'خراسان رضوی',
        'خراسان رضوی' => 'خراسان رضوی',
        'كردستان' => 'کردستان',
        'کرمانشاه' => 'کرمانشاه',
        'گيلان' => 'گیلان',
        'مازندران' => 'مازندران',
        'البرز' => 'البرز',
    ];

    public function normalizeProvince(?string $value): ?string
    {
        $value = PersianText::normalize($value);
        if ($value === null) {
            return null;
        }

        $key = PersianText::normalizeKey($value);
        if ($key === null) {
            return null;
        }

        foreach (self::PROVINCES as $province) {
            if (PersianText::normalizeKey($province) === $key) {
                return $province;
            }
        }

        foreach (self::$aliases as $alias => $canonical) {
            if (PersianText::normalizeKey($alias) === $key) {
                return $canonical;
            }
        }

        // Partial contains match only for long province names
        foreach (self::PROVINCES as $province) {
            $pKey = PersianText::normalizeKey($province);
            if ($pKey && (str_contains($key, $pKey) || str_contains($pKey, $key)) && mb_strlen($key) >= 3) {
                return $province;
            }
        }

        return null;
    }

    public function normalizeCity(?string $value): ?string
    {
        $value = PersianText::normalize($value);
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/^(شهر|شهرستان)\s+/u', '', $value) ?? $value;
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 100);
    }
}
