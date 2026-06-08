<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrialProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customerAmanah = Customer::query()->where('code', 'CUST-MSJ03')->first(); // Takmir Masjid Al-Amanah
        $customerWka = Customer::query()->where('code', 'CUST-WKA01')->first(); // PT Wijaya Karya Agung
        $admin = User::query()->where('email', 'admin@example.com')->first();

        if ($customerAmanah === null || $customerWka === null) {
            return;
        }

        // 1. PROJECT 1: Kubah GRC Al-Amanah
        $project1 = Project::query()->updateOrCreate(
            ['code' => 'PRJ-TRIAL-001'],
            [
                'customer_id' => $customerAmanah->id,
                'project_name' => 'Pemasangan Kubah GRC Masjid Al-Amanah',
                'location' => 'Gresik, Jawa Timur',
                'project_type' => 'Kubah Masjid',
                'project_spec' => 'Kubah GRC Dia 5m, Rangka Baja, Finishing Motif Klasik',
                'contract_value' => 135000000,
                'deadline' => now()->addMonths(2)->toDateString(),
                'progress' => 45,
                'status' => 'production',
            ]
        );

        // Timelines Project 1
        $project1->timelines()->delete();
        $project1->timelines()->create([
            'event_date' => now()->subDays(7)->toDateString(),
            'stage' => 'Survey Lokasi & Pengukuran',
            'description' => 'Survey lapangan, pengukuran lubang dome kubah, koordinasi struktur penahan.',
            'icon' => 'Compass',
            'created_by' => $admin?->id,
        ]);
        $project1->timelines()->create([
            'event_date' => now()->subDays(5)->toDateString(),
            'stage' => 'Penyusunan Desain & Rangka',
            'description' => 'Desain arsitektur kubah klasik disetujui takmir, mulai fabrikasi rangka baja di workshop.',
            'icon' => 'CheckCircle',
            'created_by' => $admin?->id,
        ]);
        $project1->timelines()->create([
            'event_date' => now()->subDays(2)->toDateString(),
            'stage' => 'Produksi Workshop GRC',
            'description' => 'Mulai cetak modul GRC kubah segmen demi segmen. Progres cetak modul mencapai 40%.',
            'icon' => 'CheckCircle',
            'created_by' => $admin?->id,
        ]);

        // Budgets Project 1
        $project1->budgetItems()->delete();
        $project1->budgetItems()->create([
            'component' => 'Material GRC & Rangka Baja',
            'budget_amount' => 65000000,
            'actual_amount' => 45000000,
            'notes' => 'Pembelian semen GRC, fiber glass, dan besi hollow rangka.',
        ]);
        $project1->budgetItems()->create([
            'component' => 'Upah Tenaga Kerja Workshop',
            'budget_amount' => 20000000,
            'actual_amount' => 10000000,
            'notes' => 'Upah borongan cetak dan perakitan rangka.',
        ]);
        $project1->budgetItems()->create([
            'component' => 'Transportasi & Mobilisasi Crane',
            'budget_amount' => 10000000,
            'actual_amount' => 0,
            'notes' => 'Sewa truk tronton & crane untuk pemasangan kubah di lokasi.',
        ]);

        // Termins Project 1
        $project1->termins()->delete();
        $project1->termins()->create([
            'phase' => 'DP Kontrak 30%',
            'amount' => 40500000,
            'due_date' => now()->subDays(6)->toDateString(),
            'status' => 'paid',
            'paid_at' => now()->subDays(6),
        ]);
        $project1->termins()->create([
            'phase' => 'Termin II (Saat Pengiriman Material) 40%',
            'amount' => 54000000,
            'due_date' => now()->addDays(20)->toDateString(),
            'status' => 'unpaid',
        ]);
        $project1->termins()->create([
            'phase' => 'Pelunasan & Serah Terima 30%',
            'amount' => 40500000,
            'due_date' => now()->addMonths(2)->toDateString(),
            'status' => 'unpaid',
        ]);


        // 2. PROJECT 2: Drainase U-Ditch Darmo
        $project2 = Project::query()->updateOrCreate(
            ['code' => 'PRJ-TRIAL-002'],
            [
                'customer_id' => $customerWka->id,
                'project_name' => 'Pembangunan Drainase U-Ditch Raya Darmo',
                'location' => 'Jl. Raya Darmo, Surabaya',
                'project_type' => 'Precast Concrete',
                'project_spec' => 'Pasang U-Ditch 40x40x120cm + Tutup Heavy Duty 200m',
                'contract_value' => 75000000,
                'deadline' => now()->addMonth()->toDateString(),
                'progress' => 15,
                'status' => 'survey',
            ]
        );

        // Timelines Project 2
        $project2->timelines()->delete();
        $project2->timelines()->create([
            'event_date' => now()->subDays(2)->toDateString(),
            'stage' => 'Survey Lokasi & Alinyemen Drainase',
            'description' => 'Pengukuran elevasi kemiringan tanah saluran drainase di Raya Darmo.',
            'icon' => 'Compass',
            'created_by' => $admin?->id,
        ]);

        // Budgets Project 2
        $project2->budgetItems()->delete();
        $project2->budgetItems()->create([
            'component' => 'Pengadaan Material U-Ditch & Cover',
            'budget_amount' => 42000000,
            'actual_amount' => 0,
            'notes' => 'U-Ditch 40x40x120 167 pcs & Cover Heavy Duty 167 pcs.',
        ]);
        $project2->budgetItems()->create([
            'component' => 'Upah Galian & Pemasangan Saluran',
            'budget_amount' => 18000000,
            'actual_amount' => 0,
            'notes' => 'Upah harian pekerja galian tanah & instalasi precast.',
        ]);

        // Termins Project 2
        $project2->termins()->delete();
        $project2->termins()->create([
            'phase' => 'DP Awal Kerja 50%',
            'amount' => 37500000,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'unpaid',
        ]);
        $project2->termins()->create([
            'phase' => 'Pelunasan 50% Setelah FHO',
            'amount' => 37500000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'unpaid',
        ]);
    }
}
