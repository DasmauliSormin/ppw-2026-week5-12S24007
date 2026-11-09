<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BukuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasStock = $this->stok > 0;

        $links = [
            'self' => [
                'href'   => url("/api/v1/bukus/{$this->id}"),
                'method' => 'GET',
            ],
            'update_full' => [
                'href'   => url("/api/v1/bukus/{$this->id}"),
                'method' => 'PUT',
            ],
            'update_partial' => [
                'href'   => url("/api/v1/bukus/{$this->id}"),
                'method' => 'PATCH',
            ],
            'delete' => [
                'href'   => url("/api/v1/bukus/{$this->id}"),
                'method' => 'DELETE',
            ],
        ];

        // HATEOAS: Link pinjam_buku hanya muncul jika stok > 0
        if ($hasStock) {
            $links['pinjam_buku'] = [
                'href'   => url("/api/v1/peminjamans"),
                'method' => 'POST',
            ];
        }

        return [
            'id'           => $this->id,
            'isbn'         => $this->isbn,
            'judul'        => $this->judul,
            'penulis'      => $this->penulis,
            'penerbit'     => $this->penerbit,
            'stok'         => (int) $this->stok,
            'status_label' => $hasStock ? 'AVAILABLE' : 'OUT_OF_STOCK',
            'kategori'     => $this->whenLoaded('kategori', fn() => [
                'id'           => $this->kategori->id,
                'nama_kategori'=> $this->kategori->nama_kategori,
            ]),
            'riwayat_peminjaman' => PeminjamanResource::collection($this->whenLoaded('peminjamans')),
            '_links'       => $links,
        ];
    }
}