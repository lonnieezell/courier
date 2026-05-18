<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\SegmentModel;
use RuntimeException;

/**
 * Resolves segments into contact lists.
 */
class SegmentService
{
    public function __construct(
        private readonly ContactModel $contactModel,
        private readonly SegmentModel $segmentModel,
    ) {
    }

    /**
     * Returns all subscribed contacts that have ALL the given tag slugs.
     *
     * @param list<string> $slugs
     *
     * @return list<object>
     */
    public function resolveByTagSlugs(array $slugs): array
    {
        $count = count($slugs);

        if ($count === 0) {
            return [];
        }

        $db = $this->contactModel->db;

        return $db->table('courier_contacts c')
            ->select('c.*')
            ->join('courier_contact_tags ct', 'ct.contact_id = c.id')
            ->join('courier_tags t', 't.id = ct.tag_id')
            ->whereIn('t.slug', $slugs)
            ->groupBy('c.id')
            ->having('COUNT(DISTINCT t.id) =', $count)
            ->get()
            ->getResultObject();
    }

    /**
     * Resolves a segment by ID into a list of matching subscribed contacts.
     *
     * @return list<object>
     */
    public function resolve(int $segmentId): array
    {
        return $this->buildQuery($segmentId)->get()->getResultObject();
    }

    /**
     * Returns the count of contacts matching the segment.
     */
    public function previewCount(int $segmentId): int
    {
        return $this->buildQuery($segmentId)->countAllResults();
    }

    /**
     * Yields contacts matching the segment in chunks to avoid loading the
     * full result set into memory at once. Use this instead of resolve()
     * when processing large segments.
     *
     * @return \Generator<int, list<object>>
     */
    public function resolveChunked(int $segmentId, int $chunkSize = 200): \Generator
    {
        $offset = 0;

        do {
            $rows = $this->buildQuery($segmentId)
                ->limit($chunkSize, $offset)
                ->get()
                ->getResultObject();

            if ($rows === []) {
                break;
            }

            yield $rows;

            $offset += $chunkSize;
        } while (count($rows) === $chunkSize);
    }

    /**
     * Builds the base query for a segment, applying all rules.
     */
    private function buildQuery(int $segmentId): BaseBuilder
    {
        $segment  = $this->segmentModel->find($segmentId);
        $rules    = (array) ($segment->rules ?? []);
        $matchAny = ($segment->match_mode ?? 'all') === 'any';
        $db       = $this->contactModel->db;
        $builder  = $db->table('courier_contacts c')->select('c.*');

        if ($rules === []) {
            return $builder;
        }

        if ($matchAny) {
            $builder->groupStart();
        }

        foreach ($rules as $idx => $rule) {
            $this->applyRule($builder, (object) $rule, $db, $matchAny && $idx > 0);
        }

        if ($matchAny) {
            $builder->groupEnd();
        }

        return $builder;
    }

    /** Contact columns allowed as segment rule fields. */
    private const ALLOWED_FIELDS = [
        'email', 'first_name', 'last_name', 'status', 'source',
        'subscribed_at', 'unsubscribed_at',
    ];

    private function applyRule(
        BaseBuilder $builder,
        object $rule,
        BaseConnection $db,
        bool $isOr,
    ): void {
        $field = $rule->field ?? '';
        $op    = $rule->op ?? 'eq';
        $value = $rule->value ?? '';

        if ($field === 'tag') {
            // Build raw EXISTS SQL to avoid CI4 identifier-prefix mangling
            // in correlated sub-queries that reference the outer alias 'c'.
            $p    = $db->getPrefix();
            $slug = $db->escape($value);
            $sql  = 'EXISTS ('
                . "SELECT 1 FROM {$p}courier_contact_tags _ct "
                . "INNER JOIN {$p}courier_tags _t ON _t.id = _ct.tag_id "
                . "WHERE _ct.contact_id = c.id AND _t.slug = {$slug}"
                . ')';

            $isOr ? $builder->orWhere($sql, null, false) : $builder->where($sql, null, false);

            return;
        }

        if (str_starts_with($field, 'custom:')) {
            $driver = $db->DBDriver;

            if (! in_array($driver, ['MySQLi', 'SQLite3'], true)) {
                throw new RuntimeException(
                    "custom: field rules are only supported on MySQL and SQLite drivers (got: {$driver})",
                );
            }

            $key = substr($field, 7);

            if (! preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new InvalidArgumentException("Invalid custom field key: {$key}");
            }

            $col = "JSON_EXTRACT(c.custom_fields, '$.{$key}')";
            $this->applyColumnOp($builder, $col, $op, $value, $isOr);

            return;
        }

        if (! in_array($field, self::ALLOWED_FIELDS, true)) {
            throw new InvalidArgumentException("Unknown segment field: {$field}");
        }

        $this->applyColumnOp($builder, "c.{$field}", $op, $value, $isOr);
    }

    private function applyColumnOp(
        BaseBuilder $builder,
        string $col,
        string $op,
        mixed $value,
        bool $isOr,
    ): void {
        $operators = [
            'eq'  => '=',
            'neq' => '!=',
            'gt'  => '>',
            'gte' => '>=',
            'lt'  => '<',
            'lte' => '<=',
        ];

        if (! isset($operators[$op])) {
            throw new InvalidArgumentException("Unknown operator: {$op}");
        }

        $key = "{$col} {$operators[$op]}";
        $isOr ? $builder->orWhere($key, $value) : $builder->where($key, $value);
    }
}
