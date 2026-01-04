<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlenaryMeetingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $meetings = [
                [
                    'meeting_title' => 'Plenary Meeting on Agricultural Procurement',
                    'meeting_date' => Carbon::now()->subDays(10)->toDateString(),
                    'meeting_time' => '09:00:00',
                    'location' => 'Main Conference Room',
                    'chairperson' => 'Ahmad Fauzi',
                    'secretary' => 'Rina Marlina',
                    'notes' => 'Discussion on procurement approval and allocation.',
                ],
                [
                    'meeting_title' => 'Plenary Meeting on Logistics Distribution',
                    'meeting_date' => Carbon::now()->subDays(5)->toDateString(),
                    'meeting_time' => '13:30:00',
                    'location' => 'Meeting Hall B',
                    'chairperson' => 'Budi Santoso',
                    'secretary' => 'Sari Dewi',
                    'notes' => 'Finalization of distribution locations and vendors.',
                ],
                [
                    'meeting_title' => 'Annual Plenary Evaluation Meeting',
                    'meeting_date' => Carbon::now()->subDays(1)->toDateString(),
                    'meeting_time' => '10:00:00',
                    'location' => 'Auditorium',
                    'chairperson' => 'Hendra Kurnia',
                    'secretary' => 'Maya Putri',
                    'notes' => 'Evaluation of procurement and cooperative performance.',
                ],
            ];

            foreach ($meetings as $meetingData) {
                $meetingId = DB::table('plenary_meetings')->insertGetId([
                    'meeting_title' => $meetingData['meeting_title'],
                    'meeting_date' => $meetingData['meeting_date'],
                    'meeting_time' => $meetingData['meeting_time'],
                    'location' => $meetingData['location'],
                    'chairperson' => $meetingData['chairperson'],
                    'secretary' => $meetingData['secretary'],
                    'notes' => $meetingData['notes'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $cooperatives = DB::table('cooperatives')->inRandomOrder()->limit(2)->get();
                $items = DB::table('items')->inRandomOrder()->limit(3)->get();
                $cpclDocument = DB::table('cpcl_documents')->inRandomOrder()->first();

                foreach ($cooperatives as $cooperative) {
                    foreach ($items as $item) {
                        DB::table('plenary_meeting_items')->insert([
                            'plenary_meeting_id' => $meetingId,
                            'cooperative_id' => $cooperative->id,
                            'cpcl_document_id' => $cpclDocument?->id,
                            'item_id' => $item->id,
                            'package_quantity' => rand(10, 100),
                            'note' => 'Approved during plenary meeting',
                            'location' => 'Central Warehouse',
                            'unit_price' => rand(100000, 500000),
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }

                $attendees = [
                    [
                        'name' => 'Andi Wijaya',
                        'work_unit' => 'Procurement Division',
                        'position' => 'Member',
                    ],
                    [
                        'name' => 'Dewi Lestari',
                        'work_unit' => 'Finance Division',
                        'position' => 'Member',
                    ],
                    [
                        'name' => 'Rizky Pratama',
                        'work_unit' => 'Logistics Division',
                        'position' => 'Observer',
                    ],
                ];

                foreach ($attendees as $attendee) {
                    DB::table('plenary_meeting_attendees')->insert([
                        'plenary_meeting_id' => $meetingId,
                        'name' => $attendee['name'],
                        'work_unit' => $attendee['work_unit'],
                        'position' => $attendee['position'],
                        'signature' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
        });
    }
}
