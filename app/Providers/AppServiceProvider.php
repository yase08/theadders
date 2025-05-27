<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth; // Pastikan ini diimpor
use Kreait\Firebase\ServiceAccount; // Opsional, tapi bisa lebih jelas

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(FirebaseAuth::class, function ($app) {
            // Pastikan path ke file JSON kredensial Anda benar!
            $serviceAccountPath = storage_path('app/firebase/service-account.json'); // GANTI INI

            if (!file_exists($serviceAccountPath)) {
                // Jika file tidak ada, log error atau throw exception agar jelas
                // Ini akan mencegah error yang lebih membingungkan nanti
                throw new \InvalidArgumentException("Firebase service account file not found at: " . $serviceAccountPath);
            }

            try {
                $factory = (new Factory)
                    ->withServiceAccount($serviceAccountPath);
                    // Opsional: Jika Anda juga menggunakan Realtime Database atau layanan lain
                    // ->withDatabaseUri('https://your-database-name.firebaseio.com'); // Ganti dengan URL database Anda

                return $factory->createAuth();
            } catch (\Exception $e) {
                // Tangani error saat inisialisasi factory jika perlu
                // Misalnya, log errornya
                \Log::error('Failed to initialize Firebase Admin SDK: ' . $e->getMessage());
                // Anda mungkin ingin melempar ulang exception atau mengembalikan null
                // tergantung bagaimana Anda ingin menangani kegagalan ini.
                // Untuk sekarang, kita lempar ulang agar masalahnya terlihat jelas.
                throw new \RuntimeException('Failed to initialize Firebase Admin SDK: ' . $e->getMessage(), 0, $e);
            }
        });

        // Anda juga bisa mendaftarkan Factory jika perlu di tempat lain
        // $this->app->singleton(Factory::class, function ($app) {
        //     $serviceAccountPath = storage_path('app/firebase/your-firebase-adminsdk.json');
        //     if (!file_exists($serviceAccountPath)) {
        //         throw new \InvalidArgumentException("Firebase service account file not found at: " . $serviceAccountPath);
        //     }
        //     return (new Factory)->withServiceAccount($serviceAccountPath);
        // });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}