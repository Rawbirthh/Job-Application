<?php

namespace App\Services\Candidate;

use App\Models\CandidateResume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CandidateResumeService
{
    /**
     * Upload or replace a candidate resume.
     */
    public function upload(User $user, UploadedFile $fileUploaded): CandidateResume
    {
        return DB::transaction(function () use ($user, $fileUploaded): CandidateResume {
            // Validate MIME type again (double-check safety)
            if ($fileUploaded->getMimeType() !== 'application/pdf') {
                throw new \InvalidArgumentException('The file must be a PDF.');
            }

            // Validate size again (double-check safety)
            if ($fileUploaded->getSize() > 5 * 1024 * 1024) {
                throw new \InvalidArgumentException('The file must not exceed 5MB.');
            }

            // Find existing resume for this user to delete old file if replacing
            $userId = $user->id;
            $existing = CandidateResume::query()->where('user_id', $userId)->first();
            $oldPath = $existing?->path;

            // Generate filesystem-safe path: user_id/{boundary_id}/{random_hash}.pdf
            $boundaryId = $userId % 100;
            $hash = md5(random_bytes(16));
            $path = sprintf('resumes/%s/%s.pdf', $boundaryId, $hash);

            // Store new file
            Storage::disk('local')->putFileAs(pathinfo($path, PATHINFO_DIRNAME), $fileUploaded, pathinfo($path, PATHINFO_BASENAME));

            // Get original filename stripped from path
            $originalFilename = $fileUploaded->getClientOriginalName();

            if ($existing) {
                $existing->update([
                    'path' => $path,
                    'original_filename' => $originalFilename,
                    'mime_type' => 'application/pdf',
                    'size' => $fileUploaded->getSize(),
                ]);
                $resume = $existing;

                // Clean up old file if path differs
                if ($oldPath && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
                    Storage::disk('local')->delete($oldPath);
                }
            } else {
                $resume = CandidateResume::query()->create([
                    'user_id' => $userId,
                    'path' => $path,
                    'original_filename' => $originalFilename,
                    'mime_type' => 'application/pdf',
                    'size' => $fileUploaded->getSize(),
                ]);
            }

            return $resume->fresh();
        });
    }

    /**
     * Delete a candidate resume file and record.
     */
    public function delete(CandidateResume $resume): void
    {
        // Delete file from local storage if it exists
        if (Storage::disk('local')->exists($resume->path)) {
            Storage::disk('local')->delete($resume->path);
        }

        // Force delete the record
        $resume->forceDelete();
    }
}
