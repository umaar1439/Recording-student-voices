<?php

namespace App\Support;

/**
 * Outcome of a Quran dataset import run.
 */
final class DatasetImportResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $replaced  codes of existing ayahs whose text was replaced
     */
    public function __construct(
        public readonly string $source,
        public readonly bool $dryRun = false,
        public int $inserted = 0,
        public int $updated = 0,
        public int $unchanged = 0,
        public int $skipped = 0,
        public int $invalid = 0,
        public array $errors = [],
        public array $replaced = [],
    ) {}

    public function processed(): int
    {
        return $this->inserted + $this->updated + $this->unchanged;
    }

    public function totalRows(): int
    {
        return $this->processed() + $this->skipped + $this->invalid;
    }

    public function hasChanges(): bool
    {
        return $this->inserted > 0 || $this->updated > 0;
    }

    public function summary(): string
    {
        return sprintf(
            'جديد: %d · محدَّث: %d · مستبدل: %d · دون تغيير: %d · متجاهل: %d · غير صالح: %d',
            $this->inserted,
            $this->updated,
            count($this->replaced),
            $this->unchanged,
            $this->skipped,
            $this->invalid,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => basename($this->source),
            'dry_run' => $this->dryRun,
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'skipped' => $this->skipped,
            'invalid' => $this->invalid,
            'total' => $this->totalRows(),
            'errors' => $this->errors,
            'replaced' => $this->replaced,
        ];
    }
}
