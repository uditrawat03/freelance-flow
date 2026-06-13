<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->encryptTableNotes('clients');
        $this->encryptTableNotes('invoices');
    }

    public function down(): void
    {
        $this->decryptTableNotes('clients');
        $this->decryptTableNotes('invoices');
    }

    private function encryptTableNotes(string $table): void
    {
        DB::table($table)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    if ($this->isEncryptedString($row->notes)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['notes' => Crypt::encryptString($row->notes)]);
                }
            });
    }

    private function decryptTableNotes(string $table): void
    {
        DB::table($table)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    try {
                        $notes = Crypt::decryptString($row->notes);
                    } catch (Throwable) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['notes' => $notes]);
                }
            });
    }

    private function isEncryptedString(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
