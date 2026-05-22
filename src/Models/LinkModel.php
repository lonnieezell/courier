<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\DTO\LinkDTO;
use Myth\Courier\Traits\HasDTO;

/**
 * Manages per-link click tokens and their destination URLs in courier_links.
 */
class LinkModel extends Model
{
    use HasDTO;

    protected $table           = 'courier_links';
    protected $returnType      = 'object';
    protected string $dtoClass = LinkDTO::class;
    protected $afterFind       = ['convertToDto'];
    protected $useTimestamps   = true;
    protected $allowedFields   = [
        'send_id',
        'link_token',
        'url',
    ];

    public function findByToken(string $token): ?LinkDTO
    {
        return $this->where('link_token', $token)->first();
    }

    /**
     * Bulk-inserts a token→url map for a single send.
     *
     * @param array<string, string> $links [token => url]
     */
    public function insertLinks(int $sendId, array $links): void
    {
        $rows = [];

        foreach ($links as $token => $url) {
            $rows[] = [
                'send_id'    => $sendId,
                'link_token' => $token,
                'url'        => $url,
            ];
        }

        if ($rows !== []) {
            $this->insertBatch($rows);
        }
    }
}
