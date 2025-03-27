<?php

namespace App\Http\Controllers;

use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ExportDownloadController extends Controller
{
    /**
     * Download an export file.
     *
     * @param Export $export
     * @return \Illuminate\Http\Response
     */
    public function download(Export $export)
    {
        // Check if user is authorized to download this export
        if ($export->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this export.');
        }

        // Check if export is completed
        if ($export->status !== 'completed') {
            abort(404, 'Export is not yet available for download.');
        }

        // Check if file exists
        if (!file_exists($export->file_path)) {
            abort(404, 'Export file not found.');
        }

        // Return file for download
        return Response::download(
            $export->file_path,
            $export->file_name,
            ['Content-Type' => $this->getContentType($export->format)]
        );
    }

    /**
     * Get content type for export format.
     *
     * @param string $format
     * @return string
     */
    private function getContentType(string $format): string
    {
        return match ($format) {
            'json' => 'application/json',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
} 