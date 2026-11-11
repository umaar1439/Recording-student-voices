<?php

use App\Support\WrongAyahMutator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the assignment pool used by the correct/wrong recitation tasks
     * and link existing recordings to their task.
     */
    public function up(): void
    {
        Schema::create('recitation_tasks', function (Blueprint $table) {
            $table->id();

            // Matches ayahs.id (composite mushaf identifier).
            $table->unsignedInteger('ayah_id');
            $table->foreign('ayah_id')->references('id')->on('ayahs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('type', ['correct', 'wrong']);

            // Custom dataset identifier: 001001 (correct) / 001001x (wrong).
            $table->string('code', 12)->unique();

            // Text displayed to the student and exported as the transcript.
            $table->text('text_uthmani');

            $table->enum('status', ['available', 'pending', 'approved'])->default('available');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['ayah_id', 'type'], 'recitation_tasks_ayah_type_unique');
            $table->index(['status', 'type'], 'recitation_tasks_status_type_index');
            $table->index('assigned_to');
        });

        Schema::table('recordings', function (Blueprint $table) {
            $table->foreignId('recitation_task_id')
                ->nullable()
                ->after('ayah_id')
                ->constrained('recitation_tasks')
                ->nullOnDelete();

            $table->unique(['user_id', 'recitation_task_id'], 'recordings_user_task_unique');
        });

        $this->backfillTasks();
        $this->backfillRecordings();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropUnique('recordings_user_task_unique');
            $table->dropConstrainedForeignId('recitation_task_id');
        });

        Schema::dropIfExists('recitation_tasks');
    }

    /**
     * Generate the correct/wrong task pair for every ayah already stored.
     */
    protected function backfillTasks(): void
    {
        $now = Carbon::now();
        $buffer = [];

        DB::table('ayahs')->select(['id', 'text_uthmani'])->orderBy('id')->chunkById(50, function ($ayahs) use (&$buffer, $now): void {
            foreach ($ayahs as $ayah) {
                $code = str_pad((string) (int) $ayah->id, 6, '0', STR_PAD_LEFT);

                $buffer[] = [
                    'ayah_id' => $ayah->id,
                    'type' => 'correct',
                    'code' => $code,
                    'text_uthmani' => $ayah->text_uthmani,
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $buffer[] = [
                    'ayah_id' => $ayah->id,
                    'type' => 'wrong',
                    'code' => $code.'x',
                    'text_uthmani' => WrongAyahMutator::mutate((string) $ayah->text_uthmani, (int) $ayah->id),
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($buffer) >= 100) {
                DB::table('recitation_tasks')->insert($buffer);
                $buffer = [];
            }
        });

        if ($buffer !== []) {
            DB::table('recitation_tasks')->insert($buffer);
        }
    }

    /**
     * Link already stored recordings to their task and rebuild the task state
     * from the recording statuses (approved => completed, pending => claimed).
     */
    protected function backfillRecordings(): void
    {
        if (! Schema::hasColumn('recordings', 'recitation_task_id')) {
            return;
        }

        /** @var array<string, int|null> $taskIds */
        $taskIds = [];

        DB::table('recordings')
            ->select(['id', 'ayah_id', 'type'])
            ->orderBy('id')
            ->chunkById(200, function ($recordings) use (&$taskIds): void {
                foreach ($recordings as $recording) {
                    $key = $recording->ayah_id.'-'.$recording->type;

                    if (! array_key_exists($key, $taskIds)) {
                        $taskIds[$key] = DB::table('recitation_tasks')
                            ->where('ayah_id', $recording->ayah_id)
                            ->where('type', $recording->type)
                            ->value('id');
                    }

                    if ($taskIds[$key] === null) {
                        continue;
                    }

                    DB::table('recordings')->where('id', $recording->id)->update([
                        'recitation_task_id' => $taskIds[$key],
                    ]);
                }
            });

        $approved = DB::table('recordings')
            ->where('status', 'approved')
            ->whereNotNull('recitation_task_id')
            ->get(['recitation_task_id', 'reviewed_at', 'created_at']);

        foreach ($approved as $recording) {
            DB::table('recitation_tasks')->where('id', $recording->recitation_task_id)->update([
                'status' => 'approved',
                'approved_at' => $recording->reviewed_at ?? $recording->created_at,
                'updated_at' => Carbon::now(),
            ]);
        }

        $pending = DB::table('recordings')
            ->where('status', 'pending')
            ->whereNotNull('recitation_task_id')
            ->orderBy('created_at')
            ->get(['recitation_task_id', 'user_id', 'created_at']);

        foreach ($pending as $recording) {
            DB::table('recitation_tasks')
                ->where('id', $recording->recitation_task_id)
                ->where('status', 'available')
                ->update([
                    'status' => 'pending',
                    'assigned_to' => $recording->user_id,
                    'assigned_at' => $recording->created_at ?? Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
        }
    }
};
