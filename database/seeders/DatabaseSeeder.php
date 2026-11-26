<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Payment;
use App\Models\User;
use App\Services\DataProtectionService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate([
            'faculty_id' => 'UMAK-CCIS-0001',
        ], [
            'first_name' => 'Demo',
            'middle_name' => null,
            'last_name' => 'Faculty',
            'email' => 'demo.faculty@umak.edu.ph',
            'department' => 'College of Computing and Information Sciences',
            'position' => 'Faculty Member',
            'contact_number' => '09170000001',
            'role' => User::ROLE_FACULTY,
            'status' => 'active',
            'profile_photo' => null,
            'password' => Hash::make('Faculty123'),
        ]);

        foreach ([
            [
                'faculty_id' => 'UMAK-CCIS-0002',
                'first_name' => 'Andrea',
                'middle_name' => 'Lopez',
                'last_name' => 'Santos',
                'email' => 'andrea.santos@umak.edu.ph',
                'department' => 'Computer Science',
                'position' => 'Assistant Professor',
                'contact_number' => '09170000002',
            ],
            [
                'faculty_id' => 'UMAK-CCIS-0003',
                'first_name' => 'Miguel',
                'middle_name' => null,
                'last_name' => 'Reyes',
                'email' => 'miguel.reyes@umak.edu.ph',
                'department' => 'Information Technology',
                'position' => 'Instructor',
                'contact_number' => '09170000003',
            ],
            [
                'faculty_id' => 'UMAK-CCIS-0004',
                'first_name' => 'Patricia',
                'middle_name' => 'Cruz',
                'last_name' => 'Dela Vega',
                'email' => 'patricia.delavega@umak.edu.ph',
                'department' => 'Information Systems',
                'position' => 'Associate Professor',
                'contact_number' => '09170000004',
            ],
        ] as $member) {
            User::updateOrCreate([
                'faculty_id' => $member['faculty_id'],
            ], [
                ...$member,
                'email_verified_at' => now(),
                'role' => User::ROLE_FACULTY,
                'status' => 'active',
                'profile_photo' => null,
                'password' => Hash::make('Faculty123'),
            ]);
        }

        $authorId = User::where('role', User::ROLE_ADMIN)->value('id')
            ?? User::where('faculty_id', 'UMAK-CCIS-0001')->value('id');

        $announcements = [
            [
                'title' => 'CCIS Faculty Club Monthly Meeting',
                'category' => 'Meeting',
                'audience' => 'All Faculty',
                'priority' => 'important',
                'body' => 'The CCIS Faculty Club will hold its monthly coordination meeting to discuss club updates, member concerns, upcoming activities, and collection monitoring. Faculty members are encouraged to review posted reminders before the meeting date.',
                'is_pinned' => true,
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Faculty Contribution Monitoring Reminder',
                'category' => 'Finance',
                'audience' => 'All Faculty',
                'priority' => 'normal',
                'body' => 'Monthly contribution monitoring is ongoing. Members may check their records in the portal once the collections module is enabled. For questions, coordinate with the Faculty Club Treasurer.',
                'is_pinned' => false,
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => 'Inventory Records Review for Faculty Club Assets',
                'category' => 'Inventory',
                'audience' => 'Faculty Club Officers',
                'priority' => 'normal',
                'body' => 'Officers will review available supplies and faculty club assets as preparation for the inventory management module. Updated records will support cleaner monitoring and future audit reports.',
                'is_pinned' => false,
                'published_at' => now()->subDays(9),
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::updateOrCreate(
                ['title' => $announcement['title']],
                [
                    ...$announcement,
                    'created_by' => $authorId,
                    'image_path' => null,
                    'expires_at' => null,
                ]
            );
        }

        $paymentSeeds = [
            [
                'faculty_id' => 'UMAK-CCIS-0002',
                'reference_no' => 'FC-PAY-202608-0002',
                'description' => 'Monthly Contribution - August 2026',
                'amount' => 500,
                'status' => 'paid',
                'due_date' => now()->endOfMonth()->toDateString(),
                'paid_at' => now()->subDays(4),
                'notes' => 'Recorded for monthly Faculty Club contribution.',
            ],
            [
                'faculty_id' => 'UMAK-CCIS-0003',
                'reference_no' => 'FC-PAY-202608-0003',
                'description' => 'Monthly Contribution - August 2026',
                'amount' => 500,
                'status' => 'pending',
                'due_date' => now()->endOfMonth()->toDateString(),
                'paid_at' => null,
                'notes' => 'Awaiting payment confirmation.',
            ],
            [
                'faculty_id' => 'UMAK-CCIS-0004',
                'reference_no' => 'FC-PAY-202608-0004',
                'description' => 'Faculty Activity Contribution',
                'amount' => 200,
                'status' => 'paid',
                'due_date' => now()->addDays(7)->toDateString(),
                'paid_at' => now()->subDays(1),
                'notes' => 'Contribution for upcoming CCIS Faculty Club activity.',
            ],
        ];

        foreach ($paymentSeeds as $payment) {
            $memberId = User::where('faculty_id', $payment['faculty_id'])->value('id');

            if (! $memberId) {
                continue;
            }

            Payment::updateOrCreate(
                ['reference_no_lookup' => app(DataProtectionService::class)->paymentReferenceLookup($payment['reference_no'])],
                [
                    'reference_no' => $payment['reference_no'],
                    'user_id' => $memberId,
                    'recorded_by' => $authorId,
                    'description' => $payment['description'],
                    'amount' => $payment['amount'],
                    'status' => $payment['status'],
                    'due_date' => $payment['due_date'],
                    'paid_at' => $payment['paid_at'],
                    'notes' => $payment['notes'],
                ]
            );
        }
    }
}
