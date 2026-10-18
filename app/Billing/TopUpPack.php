<?php

namespace App\Billing;

final readonly class TopUpPack
{
    public function __construct(
        public string $key,
        public string $name,
        public int $tokens,
        public int $price,
    ) {}

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        $packs = [];

        foreach ((array) config('ulvi.top_up_packs') as $key => $pack) {
            $packs[] = new self($key, $pack['name'], $pack['tokens'], $pack['price']);
        }

        return $packs;
    }

    public static function find(string $key): ?self
    {
        foreach (self::all() as $pack) {
            if ($pack->key === $key) {
                return $pack;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys((array) config('ulvi.top_up_packs'));
    }
}
