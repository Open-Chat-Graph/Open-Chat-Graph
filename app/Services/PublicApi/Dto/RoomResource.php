<?php

declare(strict_types=1);

namespace App\Services\PublicApi\Dto;

final readonly class RoomResource implements \JsonSerializable
{
    /** @param string[] $themes */
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public int $memberCount,
        public ?array $category,
        public array $themes,
        public string $verificationType,
        public string $joinMethod,
        public ?int $change1h,
        public ?int $change24h,
        public ?int $change7d,
        public ?string $openedAt,
        public string $observedAt,
        public string $dataUpdatedAt,
        public string $canonicalUrl,
        public string $lineTransitionUrl,
        public string $methodologyUrl,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'member_count' => $this->memberCount,
            'category' => $this->category,
            'themes' => $this->themes,
            'verification_type' => $this->verificationType,
            'join_method' => $this->joinMethod,
            'changes' => [
                'one_hour' => $this->change1h,
                'twenty_four_hours' => $this->change24h,
                'seven_days' => $this->change7d,
            ],
            'opened_at' => $this->openedAt,
            'observed_at' => $this->observedAt,
            'data_updated_at' => $this->dataUpdatedAt,
            'canonical_url' => $this->canonicalUrl,
            'line_transition_url' => $this->lineTransitionUrl,
            'methodology_url' => $this->methodologyUrl,
        ];
    }
}
