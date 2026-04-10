<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository::class)]
#[ORM\Table(name: 'ca_analytics_event')]
#[ORM\Index(columns: ['fingerprint'], name: 'idx_event_fingerprint')]
#[ORM\Index(columns: ['recorded_at'], name: 'idx_event_recorded_at')]
#[ORM\Index(columns: ['name'], name: 'idx_event_name')]
class AnalyticsEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $fingerprint;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $value;

    #[ORM\Column(type: Types::STRING, length: 2048, name: 'page_url')]
    private string $pageUrl;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'recorded_at')]
    private \DateTimeImmutable $recordedAt;

    private function __construct(
        string $fingerprint,
        string $name,
        ?string $value,
        string $pageUrl,
        \DateTimeImmutable $recordedAt,
    ) {
        $this->fingerprint = $fingerprint;
        $this->name = $name;
        $this->value = $value;
        $this->pageUrl = $pageUrl;
        $this->recordedAt = $recordedAt;
    }

    public static function create(
        string $fingerprint,
        string $name,
        ?string $value,
        string $pageUrl,
        \DateTimeImmutable $recordedAt,
    ): self {
        return new self($fingerprint, $name, $value, $pageUrl, $recordedAt);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getPageUrl(): string
    {
        return $this->pageUrl;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
