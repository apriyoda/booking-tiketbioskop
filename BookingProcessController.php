<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Booking;
use App\Models\BookedSeat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingProcessController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Menampilkan halaman pemilihan kursi untuk jadwal tertentu.
     */
    public function showSeats(Schedule $schedule)
    {
        $schedule->load('film', 'studio');
        $bookedSeats = BookedSeat::where('schedule_id', $schedule->id)
                                ->pluck('seat_identifier')
                                ->toArray();
        return view('booking.seats', compact('schedule', 'bookedSeats'));
    }

    /**
     * Memproses pemilihan kursi dan membuat booking awal dengan status 'pending'.
     * Kemudian mengarahkan pengguna ke halaman pembayaran.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Schedule  $schedule
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processBooking(Request $request, Schedule $schedule)
    {
        $request->validate([
            'selected_seats' => 'required|array|min:1',
            'selected_seats.*' => 'string|max:5',
        ]);

        $user = Auth::user();
        $selectedSeats = $request->input('selected_seats');
        $totalPrice = $schedule->harga_tiket * count($selectedSeats);

        DB::beginTransaction();

        try {
            $alreadyBooked = BookedSeat::where('schedule_id', $schedule->id)
                                        ->whereIn('seat_identifier', $selectedSeats)
                                        ->count();

            if ($alreadyBooked > 0) {
                DB::rollBack();
                return back()->with('error', 'Beberapa kursi yang Anda pilih sudah tidak tersedia. Silakan pilih kursi lain.');
            }

            $kodeReservasi = 'TIX-' . Str::upper(Str::random(8));

            // Buat booking dengan status 'pending'
            $booking = Booking::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->id,
                'kode_reservasi' => $kodeReservasi,
                'total_harga' => $totalPrice,
                'status' => 'pending', // <--- UBAH STATUS INI KE 'pending'
            ]);

            $bookedSeatsData = [];
            foreach ($selectedSeats as $seatIdentifier) {
                $bookedSeatsData[] = [
                    'booking_id' => $booking->id,
                    'schedule_id' => $schedule->id,
                    'seat_identifier' => $seatIdentifier,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            BookedSeat::insert($bookedSeatsData);

            DB::commit();

            // Redirect ke halaman pembayaran, membawa ID booking
            return redirect()->route('booking.show_payment', $booking->id)->with('success', 'Pemesanan berhasil dibuat! Silakan lanjutkan ke pembayaran.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat membuat pesanan: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan halaman konfirmasi pembayaran.
     *
     * @param  \App\Models\Booking  $booking
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showPaymentForm(Booking $booking)
    {
        // Pastikan booking adalah milik user yang sedang login dan statusnya 'pending'
        if ($booking->user_id !== Auth::id() || $booking->status !== 'pending') {
            return redirect()->route('user.bookings.index')->with('error', 'Pesanan tidak valid untuk pembayaran atau sudah dibayar.');
        }

        $booking->load(['schedule.film', 'schedule.studio', 'bookedSeats']);

        return view('booking.payment', compact('booking'));
    }

    /**
     * Memproses pembayaran simulasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Booking  $booking
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPayment(Request $request, Booking $booking)
    {
        // Pastikan booking adalah milik user yang sedang login dan statusnya 'pending'
        if ($booking->user_id !== Auth::id() || $booking->status !== 'pending') {
            return redirect()->route('user.bookings.index')->with('error', 'Pesanan tidak valid untuk pembayaran atau sudah dibayar.');
        }

        $request->validate([
            'payment_status' => 'required|in:success,failed', // Simulasi status pembayaran
        ]);

        DB::beginTransaction();

        try {
            if ($request->input('payment_status') === 'success') {
                $booking->status = 'paid';
                $booking->save();
                DB::commit();
                return redirect()->route('user.bookings.index')->with('success', 'Pembayaran berhasil! Tiket Anda telah dikonfirmasi. Kode Reservasi: ' . $booking->kode_reservasi);
            } else {
                $booking->status = 'cancelled'; // Jika pembayaran gagal, batalkan pesanan
                $booking->save();
                DB::commit();
                return redirect()->route('user.bookings.index')->with('error', 'Pembayaran gagal. Pesanan Anda telah dibatalkan.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat memproses pembayaran: ' . $e->getMessage());
        }
    }
}
