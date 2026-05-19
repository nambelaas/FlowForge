<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WorkflowAIService
{
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        // Menggunakan endpoint Gemini 2.5 Flash yang sangat cepat dan pas untuk teks analisis
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    /**
     * Meminta analisis ke Gemini AI berdasarkan log error yang terjadi
     */
    public function analyzeFailure(string $stepType, string $errorLogs): string
    {
        if (!$this->apiKey) {
            return "Analisis AI tidak tersedia: GEMINI_API_KEY belum dikonfigurasi di file .env.";
        }

        // Buat prompt yang ketat agar AI fokus memberikan solusi teknis yang ringkas
        $prompt = "Kamu adalah seorang pakar DevOps dan Backend Engineer Senior. "
            . "Sebuah langkah dalam sistem Workflow Automation gagal dieksekusi.\n\n"
            . "Informasi Langkah:\n"
            . "- Tipe Langkah: {$stepType}\n"
            . "- Log Kesalahan (Error Logs):\n\"\"\"\n{$errorLogs}\n\"\"\"\n\n"
            . "Tolong berikan analisis singkat maksimal 3 kalimat yang berisi:\n"
            . "1. Apa kemungkinan besar penyebab utama eror ini terjadi.\n"
            . "2. Langkah taktis apa yang harus dilakukan oleh tim developer untuk memperbaikinya.\n"
            . "Berikan jawaban langsung dalam Bahasa Indonesia yang santun, ringkas, dan mudah dipahami.";

        try {
            $response = Http::timeout(15)->post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                throw new Exception("Gemini API returned status " . $response->status());
            }

            $result = $response->json();

            // Ekstrak teks jawaban dari struktur JSON respon Gemini
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Gagal mengekstrak analisis dari AI.';
        } catch (Exception $e) {
            Log::error("WorkflowAIService Error: " . $e->getMessage());
            return "Gagal mendapatkan analisis otomatis dari AI karena kendala koneksi ke layanan LLM.";
        }
    }
}
