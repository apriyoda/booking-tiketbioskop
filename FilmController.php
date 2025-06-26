<?php

namespace App\Http\Controllers;

use App\Models\Film;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Penting: Pastikan ini diimpor untuk penggunaan Storage::url() di Blade

class FilmController extends Controller
{
    /**
     * Menampilkan daftar film yang sedang tayang untuk pengguna publik.
     * Film yang ditampilkan adalah yang memiliki status 'sedang_tayang' true.
     */
    public function index()
    {
        // Mengambil film yang sedang tayang, diurutkan berdasarkan judul, dan dipaginasi
        $films = Film::where('sedang_tayang', true)
                     ->orderBy('judul')
                     ->paginate(8); // Menampilkan 8 film per halaman

        // Mengirim data film ke view 'films.index'
        return view('films.index', compact('films'));
    }

    /**
     * Menampilkan detail film tertentu beserta jadwal tayangnya.
     *
     * @param  \App\Models\Film  $film  Model Film yang diresolve otomatis oleh Laravel
     */
    public function show(Film $film)
    {
        // Mengambil jadwal tayang yang akan datang untuk film ini
        // Menggunakan with('studio') untuk memuat relasi studio agar tidak ada N+1 query
        // Memfilter jadwal yang tanggal tayangnya lebih besar atau sama dengan hari ini
        // Mengurutkan berdasarkan tanggal dan waktu, lalu mengelompokkan berdasarkan tanggal
        $schedules = $film->schedules()
                          ->with('studio') // Memuat relasi Studio untuk setiap jadwal
                          ->where('tanggal_tayang', '>=', now()->toDateString()) // Hanya jadwal yang akan datang
                          ->orderBy('tanggal_tayang') // Urutkan berdasarkan tanggal
                          ->orderBy('waktu_mulai') // Urutkan berdasarkan waktu mulai
                          ->get()
                          // Mengelompokkan jadwal berdasarkan tanggal tayang
                          // Ini berguna untuk menampilkan jadwal per hari di view
                          ->groupBy(function($date) {
                              return $date->tanggal_tayang->format('Y-m-d');
                          });

        // Mengirim data film dan jadwal ke view 'films.show'
        return view('films.show', compact('film', 'schedules'));
    }
}
