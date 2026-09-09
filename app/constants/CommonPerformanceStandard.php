<?php

namespace App\constants;

class CommonPerformanceStandard
{
    public static function all(): array
    {
        return [
        'Office Reports (simple) - routinary, monthly, quarterly, weekly, daily' => [
              
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revision',
                        2 => 'With 3 revision',
                        1 => 'With 4 revision and beyond',
                    ],
                    'timeliness' => [
                        5 => 'Before the deadline',
                        4 => '2-5 days before the deadline',
                        3 => 'Within the deadline',
                        2 => '2-5 days after the deadline',
                        1 => 'After deadline',
                    ],
                    'required_output' => 'Simple Reports',
                
            ],

            'Office Reports (complex) - accomplishments, annual, semestral' => [
               
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revision',
                        2 => 'With 3 revision',
                        1 => 'With 4 revision and beyond',
                    ],
                    'timeliness' => [
                        5 => '6-10 days before the deadline',
                        4 => '2-5 days before the deadline',
                        3 => 'Within the deadline',
                        2 => '2-5 days after the deadline',
                        1 => '6 days or more after the deadline',
                    ],
                    'required_output' => 'Complex Reports',
                
            ],

            'Terminal Reports (as participant) - narrative, terminal' => [
          
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revision',
                        2 => 'With 3 revision',
                        1 => 'With 4 revision and beyond',
                    ],
                    'timeliness' => [
                        5 => '1 day after the activity',
                        4 => '2 days after the activity',
                        3 => '3 days after the activity',
                        2 => '4 days after the activity',
                        1 => '5 days after the activity',
                    ],
                    'required_output' => 'Terminal Report as Participant',
                
            ],

            'Terminal Reports (as organizer) - narrative, terminal' => [
               
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revision',
                        2 => 'With 3 revision',
                        1 => 'With 4 revision and beyond',
                    ],
                    'timeliness' => [
                        5 => '10 days after the activity',
                        4 => '15 days after the activity',
                        3 => '20 days after the activity',
                        2 => '25 days after the activity',
                        1 => '30 days after the activity',
                    ],
                    'required_output' => 'Terminal Report as Organizer',
                
            ],

            'Correspondence - simple letters, memos, emails, messages' => [
           
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revision',
                        2 => 'With 3 revision',
                        1 => 'With 4 revision and beyond',
                    ],
                    'timeliness' => [
                        5 => 'Within 15 minutes after receipt of instruction',
                        4 => 'Within 20 minutes after receipt of instruction',
                        3 => 'Within 25 minutes after receipt of instruction',
                        2 => 'Within 30 minutes after receipt of instruction',
                        1 => 'Beyond 31 minutes after receipt of instruction',
                    ],
                    'required_output' => 'Preparation of simple letters, memos, emails, messages',
                
            ],

            'Correspondence - complex letters, executive orders, resolutions' => [
           
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revision',
                        2 => 'With 3 revision',
                        1 => 'With 4 revision and beyond',
                    ],
                    'timeliness' => [
                        5 => 'Within 1 day after receipt of instruction',
                        4 => 'Within 2 days after receipt of instruction',
                        3 => 'Within 3 days after receipt of instruction',
                        2 => 'Within 4 days after receipt of instruction',
                        1 => '5 days or more after receipt of instruction',
                    ],
                    'required_output' => 'Preparation of long letters, executive orders, resolutions',
                
            ],

            'Opinions/Comments' => [
              
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revision',
                        2 => 'With 3 revision',
                        1 => 'With 4 revision and beyond',
                    ],
                    'timeliness' => [
                        5 => 'Within 8 hours after receipt of instruction',
                        4 => 'Within 10 hours after receipt of instruction',
                        3 => 'Within 12 hours after receipt of instruction',
                        2 => 'Within 14 hours after receipt of instruction',
                        1 => '16 hours and beyond after receipt of instruction',
                    ],
                    'required_output' => 'Preparation of letters citing references, letters subject for research, with consultation',
            
            ],

            'Contract/MOU/MOA' => [
            
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revision',
                        2 => 'With 3 revision',
                        1 => 'With 4 revision and beyond',
                    ],
                    'timeliness' => [
                        5 => 'Within 4 days after receipt of instruction',
                        4 => 'Within 5 days after receipt of instruction',
                        3 => 'Within 6 days after receipt of instruction',
                        2 => 'Within 7 days after receipt of instruction',
                        1 => '8 days and beyond after receipt of instruction',
                    ],
                    'required_output' => 'Preparation of General types of contract / MOU or MOA',
                
            ],

            'Order of Payment' => [

                'effectiveness' => [
                    5 => 'Without error',
                    4 => 'With 1 error',
                    3 => 'With 2 errors',
                    2 => 'With 3 errors',
                    1 => 'With 4 errors and beyond',
                ],
                'timeliness' => [
                    5 => 'Within 3 minutes after receipt of request',
                    4 => 'Within 4 minutes after receipt of request',
                    3 => 'Within 5 minutes after receipt of request',
                    2 => 'Within 6 minutes after receipt of request',
                    1 => '7 minutes and beyond after receipt of request',
                ],
                'required_output' => 'Preparation of order of payment',

            ],

            'Entry of Incoming and Outgoing Communications' => [

                'effectiveness' => [
                    5 => 'Without error',
                    4 => 'With 1 error',
                    3 => 'With 2 errors',
                    2 => 'With 3 errors',
                    1 => 'With 4 errors and beyond',
                ],
                'timeliness' => [
                    5 => 'Within 3 minutes after receipt of communication',
                    4 => 'Within 4 minutes after receipt of communication',
                    3 => 'Within 5 minutes after receipt of communication',
                    2 => 'Within 10 minutes after receipt of communication',
                    1 => '15 minutes and beyond after receipt of communication',
                ],
                'required_output' => 'Recording of communications in the Logbook or System',
            ],

            'Financial Document (Actual)' => [

                
                    'effectiveness' => [
                        5 => 'Without revision & errors',
                        4 => ' ',
                        3 => ' ',
                        2 => 'With revision & errors',
                        1 => ' ',
                    ],
                    'timeliness' => [
                        5 => 'Within 3 hours',
                        4 => ' ',
                        3 => 'Within 6 hours',
                        2 => ' ',
                        1 => 'Beyond 6 hours',
                    ],
                    'required_output' => 'Refers to the actual preparation of each document with attachments',
            
                'Financial Document (Processing)' => [
                    'effectiveness' => [
                        5 => 'Without lapses',
                        4 => ' ',
                        3 => ' ',
                        2 => 'With lapses',
                        1 => ' ',
                    ],
                    'timeliness' => [
                        5 => 'Before the scheduled time',
                        4 => ' ',
                        3 => 'On time',
                        2 => ' ',
                        1 => 'Beyond the scheduled time',
                    ],
                    'required_output' => 'Refers to the processing of each document with attachments',
                ],
            ],

            'Annual Budget Proposal/ABC/PPMP' => [

                'effectiveness' => [
                    5 => 'With 1 revision',
                    4 => 'With 2 revisions',
                    3 => 'With 3 revisions',
                    2 => 'With 4 revisions',
                    1 => 'With 5 revisions and beyond',
                ],
                'timeliness' => [
                    5 => 'Before the deadline',
                    4 => ' ',
                    3 => 'On the set deadline',
                    2 => ' ',
                    1 => 'Late submission',
                ],
                'required_output' => 'Set deadline refers to the budget call regular budget preparation',

            ],

            'Liquidation Report' => [

                'effectiveness' => [
                    5 => 'Without revision',
                    4 => ' ',
                    3 => ' ',
                    2 => 'With revision',
                    1 => ' ',
                ],
                'timeliness' => [
                    5 => 'Within 8 hours provided with complete attachments',
                    4 => 'Within 10 hours provided with complete attachments',
                    3 => 'Within 12 hours provided with complete attachments',
                    2 => 'Within 14 hours provided with complete attachments',
                    1 => '16 hours and beyond provided with complete attachments',
                ],
                'required_output' => 'Preparation of Liquidation Report',
            ],

            'Preventive Maintenance of Service Vehicle' => [

                'effectiveness' => [
                    5 => 'Without lapses',
                    4 => ' ',
                    3 => ' ',
                    2 => 'With lapses',
                    1 => ' ',
                ],
                'timeliness' => [
                    5 => 'Before schedule',
                    4 => ' ',
                    3 => 'Within schedule',
                    2 => ' ',
                    1 => 'Beyond schedule',
                ],
                'required_output' => 'Conduct of vehicle maintenance in the assumption that this pertains to periodic maintenance',
            ],

            'Service Vehicle Trips or Driving Services' => [

                'effectiveness' => [
                    5 => 'Within conformity',
                    4 => ' ',
                    3 => ' ',
                    2 => 'With inconformities',
                    1 => ' ',
                ],
                'timeliness' => [
                    5 => 'Before schedule',
                    4 => ' ',
                    3 => 'Within schedule',
                    2 => ' ',
                    1 => 'Beyond schedule',
                ],
                'required_output' => 'Timeliness refers to the call time set by the DH/Supervisor, gasoline in full tank and ready to go to the identified destination',

            ],

            'Cleaning Services (Office & other surroundings)' => [

            
                    'effectiveness' => [
                        5 => 'Without lapses',
                        4 => ' ',
                        3 => ' ',
                        2 => 'With lapses',
                        1 => ' ',
                    ],
                    'timeliness' => [
                        5 => 'Before schedule',
                        4 => ' ',
                        3 => 'Within schedule',
                        2 => ' ',
                        1 => 'Beyond schedule',
                    ],
                    'required_output' => 'Timeliness refers to CRs being used by the office',
                

    
                
            ],
                 'Clearning Services (Comfort Room)' => [
                    'effectiveness' => [
                        5 => 'Without lapses',
                        4 => ' ',
                        3 => ' ',
                        2 => 'With lapses',
                        1 => ' ',
                    ],
                    'timeliness' => [
                        5 => 'Before schedule',
                        4 => ' ',
                        3 => 'Within schedule',
                        2 => ' ',
                        1 => 'Beyond schedule',
                    ],
                    'required_output' => 'Timeliness refers to the availability of the document',
                ],

            'Activity/Training Design' => [

                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => 'With 1 revision',
                        3 => 'With 2 revisions',
                        2 => 'With 3 revisions',
                        1 => 'With 4 revisions and beyond',
                    ],
                    'timeliness' => [
                        5 => '15 days before the activity',
                        4 => '10 days before the activity',
                        3 => '5 days before the activity',
                        2 => '2 days before the activity',
                        1 => 'On the day of the activity',
                    ],
                    'required_output' => 'Preparation of the Project Design',

                ],

            'Project Design' => [

                'effectiveness' => [
                    5 => 'With 1 revision',
                    4 => 'With 2 revisions',
                    3 => 'With 3 revisions',
                    2 => 'With 4 revisions',
                    1 => 'With 5 revisions',
                ],
                'timeliness' => [
                    5 => '5 working days',
                    4 => '8 working days',
                    3 => '10 working days',
                    2 => '12 working days',
                    1 => '15 working days',
                ],
                'required_output' => 'Preparation of the Project Design',
            ],

            'Powerpoint Presentation/Other Technology (simple) - text only' => [

                
                    'effectiveness' => [
                        5 => 'w/o revision',
                        4 => 'w/ 1 revision',
                        3 => 'w/ 2 revisions',
                        2 => 'w/ 3 revisions',
                        1 => 'w/ 4 revisions and beyond',
                    ],
                    'timeliness' => [
                        5 => 'Before schedule',
                        4 => ' ',
                        3 => 'Within schedule',
                        2 => ' ',
                        1 => 'Beyond schedule',
                    ],
                    'required_output' => 'Timeliness for this section refers to preparation of PPT Presentation per slide',
                

               
            ],
             'Powerpoint Presentation/Other Technology (complex) - e.g. with 3D effects' => [
                    'effectiveness' => [
                        5 => 'w/ 1 revision',
                        4 => 'w/ 2 revisions',
                        3 => 'w/ 3 revisions',
                        2 => 'w/ 4 revisions and beyond',
                        1 => 'w/ 5 revisions and beyond',
                    ],
                    'timeliness' => [
                        5 => 'Before schedule',
                        4 => ' ',
                        3 => 'Within schedule',
                        2 => ' ',
                        1 => 'Beyond schedule',
                    ],
                    'required_output' => 'Timeliness for this section refers to preparation of PPT Presentation per slide',
                ],

            'Minutes of Meeting (simple) - 4 hours and below' => [
             
                    'effectiveness' => [
                        5 => 'w/ 1 revision',
                        4 => 'w/ 2 revisions',
                        3 => 'w/ 3 revisions',
                        2 => 'w/ 4 revisions',
                        1 => 'w/ 5 revisions and beyond',
                    ],
                    'timeliness' => [
                        5 => '1 day after the meeting',
                        4 => '2 days after the meeting',
                        3 => '3 days after the meeting',
                        2 => '4 days after the meeting',
                        1 => '5 days and beyond',
                    ],
                    'required_output' => 'Timeliness refers to the preparation of minutes of meeting',
            ],
                'Minutes of Meeting (complex) - beyond 4 hours' => [
                    'effectiveness' => [
                        5 => 'w/ 1 revision',
                        4 => 'w/ 2 revisions',
                        3 => 'w/ 3 revisions',
                        2 => 'w/ 4 revisions',
                        1 => 'w/ 5 revisions and beyond',
                    ],
                    'timeliness' => [
                        5 => '5 days after the meeting',
                        4 => '6 days after the meeting',
                        3 => '7 days after the meeting',
                        2 => '8 days after the meeting',
                        1 => '9 days and beyond',
                    ],
                    'required_output' => 'Timeliness refers to the preparation of minutes of meeting',
                ],

            'Meetings, Seminars and/or Conferences (Conduted)' => [

                
                    'effectiveness' => [
                        5 => 'Without lapses',
                        4 => ' ',
                        3 => 'With 1 lapse',
                        2 => ' ',
                        1 => 'With more than 1 lapse',
                    ],
                    'timeliness' => [
                        5 => 'Before schedule',
                        4 => ' ',
                        3 => 'Within schedule',
                        2 => ' ',
                        1 => 'After schedule',
                    ],
                    'required_output' => 'Arrival at the venue before the scheduled time',
            

                
            ],
            'Meetings, Seminars and/or Conferences (Attended)' => [
                    'effectiveness' => [
                        5 => 'Full attendance',
                        4 => ' ',
                        3 => ' ',
                        2 => ' ',
                        1 => 'Non-Attendance',
                    ],
                    'timeliness' => [
                        5 => 'Before schedule',
                        4 => ' ',
                        3 => 'Within schedule',
                        2 => ' ',
                        1 => 'After schedule',
                    ],
                    'required_output' => 'Arrival at the venue before the scheduled time',
                ],

            'Daily Time Record (DTR) - printing for Biometric Administrators' => [

               
                    'effectiveness' => [
                        5 => 'Without revision',
                        4 => ' ',
                        3 => ' ',
                        2 => 'With revision',
                        1 => ' ',
                    ],
                    'timeliness' => [
                        5 => 'Within 1st working day of the succeeding month',
                        4 => 'Within 2nd working day of the succeeding month',
                        3 => 'Within 3rd working day of the succeeding month',
                        2 => 'Within 4th working day of the succeeding month',
                        1 => '5th working day and beyond of the succeeding month',
                    ],
                    'required_output' => 'Actual printing of DTRs',
            
            ],
               'Daily Time Record (DTR) - printing of DTRs for Employees' => [
                    'effectiveness' => [
                        5 => 'Without lapses',
                        4 => ' ',
                        3 => ' ',
                        2 => 'With lapses',
                        1 => ' ',
                    ],
                    'timeliness' => [
                        5 => 'Within 3 minutes upon receipt of DTR',
                        4 => 'Within 4 minutes upon receipt of DTR',
                        3 => 'Within 5 minutes upon receipt of DTR',
                        2 => 'Within 6 minutes upon receipt of DTR',
                        1 => '7 minutes & beyond upon receipt of DTR',
                    ],
                    'required_output' => 'Actual signing of DTR from the concerned employee',
                ],

            'Monitoring and Coaching' => [

                'effectiveness' => [
                    5 => 'Without lapses',
                    4 => ' ',
                    3 => ' ',
                    2 => 'With lapses',
                    1 => ' ',
                ],
                'timeliness' => [
                    5 => 'Within the week the M&C was conducted',
                    4 => ' ',
                    3 => 'Within the month',
                    2 => ' ',
                    1 => 'Within the semester',
                ],
                'required_output' => 'Conduct of Monitoring and Coaching Activity & Preparation of Coaching Report',

            ],

            'Monthly Performance Output Report (MPOR)' => [

                'effectiveness' => [
                    5 => 'Without revision',
                    4 => ' ',
                    3 => ' ',
                    2 => 'With revision',
                    1 => ' ',
                ],
                'timeliness' => [
                    5 => 'Within 1st working day of the succeeding month',
                    4 => 'Within 2nd working day of the succeeding month',
                    3 => 'Within 3rd working day of the succeeding month',
                    2 => 'Within 4th working day of the succeeding month',
                    1 => '5th working day and beyond of the succeeding month',
                ],
                'required_output' => 'Preparation of MPORs / Office Level Submission',

            ],

            'Individual Performance Commitment and Review (IPCR)' => [

                'effectiveness' => [
                    5 => 'With complete attachments',
                    4 => ' ',
                    3 => 'With lacking attachments',
                    2 => ' ',
                    1 => ' ',
                ],
                'timeliness' => [
                    5 => 'Before the deadline',
                    4 => ' ',
                    3 => 'Within January 15 or July 15',
                    2 => ' ',
                    1 => 'Beyond the deadline',
                ],
                'required_output' => 'Submission of IPCRs / CHRMO Submission',

            ],

            'Office Performance Commitment and Review (OPCR)' => [

                'effectiveness' => [
                    5 => 'Without revisions',
                    4 => ' ',
                    3 => 'With 1 revision',
                    2 => ' ',
                    1 => 'With 2 or more revisions',
                ],
                'timeliness' => [
                    5 => 'Before the deadline set',
                    4 => ' ',
                    3 => 'On the deadline set',
                    2 => ' ',
                    1 => 'Beyond the deadline set',
                ],
                'required_output' => 'Preparation of OPCRs / CPDO Submission',

            ],

            'Health and Wellness' => [

                'effectiveness' => [
                    5 => 'Without lapses',
                    4 => ' ',
                    3 => 'With lapses',
                    2 => ' ',
                    1 => ' ',
                ],
                'timeliness' => [
                    5 => 'Before the scheduled time',
                    4 => ' ',
                    3 => 'As per scheduled time',
                    2 => ' ',
                    1 => 'Beyond the scheduled time',
                ],
                'required_output' => 'As per office schedule (98% of the total 24 hours)',

            ],
        ];
    }

public static function get(string $output, ?string $type = null): array
{
    $data = self::all();

    if (!isset($data[$output])) {
        return [];
    }

    $outputData = $data[$output];

    // kung walang 'effectiveness' key nang direkta, ibig sabihin naka-type structure ito (simple/complex)
    $hasTypes = !isset($outputData['effectiveness']);

    if ($hasTypes) {
        // may type na binigay, ibalik yung specific na data
        if ($type) {
            return $outputData[$type] ?? [];
        }

        // walang type, ipakita lang muna kung anu-ano ang available types
        return ['type' => array_keys($outputData)];
    }

    // flat structure na (walang sub-type), diretso na lang ibalik
    return $outputData;
}
}
