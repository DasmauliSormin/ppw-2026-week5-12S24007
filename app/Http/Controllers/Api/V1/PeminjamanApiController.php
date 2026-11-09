<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PeminjamanResource;
use App\Models\Buku;
use App\Models\Peminjaman;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PeminjamanApiController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/peminjamans
     * Menampilkan daftar peminjaman terpaginasi dengan filter status & NIM
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = Peminjaman::with('buku')
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->query('nim'), fn($q, $nim) => $q->where('nim_peminjam', $nim))
            ->latest()
            ->paginate(10);

        return $this->successResponse(
            PeminjamanResource::collection($paginator->items()),
            'Daftar transaksi peminjaman berhasil diambil.'
        );
    }

    /**
     * POST /api/v1/peminjamans
     * Transaksi Peminjaman ACID dengan Pessimistic Locking (lockForUpdate)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'buku_id'       => 'required|exists:bukus,id',
            'nim_peminjam'  => 'required|string|max:15',
            'nama_peminjam' => 'required|string|max:150',
            'durasi_hari'   => 'nullable|integer|min:1|max:30',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Lock baris buku (Pessimistic Locking untuk mencegah Race Condition)
            $buku = Buku::where('id', $validated['buku_id'])->lockForUpdate()->first();

            if (!$buku) {
                return $this->problemResponse('Not Found', 'Buku tidak ditemukan.', 404, 'book-not-found');
            }

            // 2. Cek Stok < 1 (422 Unprocessable Content)
            if ($buku->stok < 1) {
                return $this->problemResponse('Quota Exhausted', "Stok buku '{$buku->judul}' sedang habis.", 422, 'stock-empty');
            }

            // 3. Cek Mahasiswa masih meminjam buku yang sama & belum kembali (409 Conflict)
            $sedangPinjam = Peminjaman::where('buku_id', $buku->id)
                ->where('nim_peminjam', $validated['nim_peminjam'])
                ->where('status', 'dipinjam')
                ->exists();

            if ($sedangPinjam) {
                return $this->problemResponse('Duplicate Loan Conflict', "Mahasiswa NIM '{$validated['nim_peminjam']}' sedang meminjam buku ini.", 409, 'duplicate-loan');
            }

            // 4. Potong Stok (-1) & Catat Peminjaman secara Atomik
            $buku->decrement('stok');

            $durasi = $validated['durasi_hari'] ?? 7;
            $peminjaman = Peminjaman::create([
                'kode_pinjam'         => 'PNJ-' . strtoupper(Str::random(8)),
                'buku_id'             => $buku->id,
                'nim_peminjam'        => strtoupper(trim($validated['nim_peminjam'])),
                'nama_peminjam'       => $validated['nama_peminjam'],
                'tanggal_pinjam'      => now()->toDateString(),
                'tanggal_jatuh_tempo' => now()->addDays($durasi)->toDateString(),
                'status'              => 'dipinjam',
            ]);

            return $this->createdResponse(
                new PeminjamanResource($peminjaman),
                url("/api/v1/peminjamans/{$peminjaman->id}"),
                "Peminjaman berhasil. Sisa stok buku: {$buku->fresh()->stok}."
            );
        });
    }

    /**
     * PATCH /api/v1/peminjamans/{id}/kembalikan
     * Pengembalian Buku ACID + lockForUpdate
     */
    public function kembalikan(string $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $peminjaman = Peminjaman::find($id);

            if (!$peminjaman) {
                return $this->problemResponse('Not Found', 'Transaksi peminjaman tidak ditemukan.', 404, 'loan-not-found');
            }

            if ($peminjaman->status === 'dikembalikan') {
                return $this->problemResponse('Loan Already Returned', 'Buku pada transaksi ini sudah dikembalikan sebelumnya.', 409, 'already-returned');
            }

            $buku = Buku::where('id', $peminjaman->buku_id)->lockForUpdate()->first();

            $peminjaman->update([
                'status'          => 'dikembalikan',
                'tanggal_kembali' => now()->toDateString(),
            ]);

            if ($buku) {
                $buku->increment('stok');
            }

            return $this->successResponse(new PeminjamanResource($peminjaman), 'Buku berhasil dikembalikan.');
        });
    }
}