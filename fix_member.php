<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Fix member 4 sponsor_id
$member = App\Models\Member::find(4);
if ($member) {
    $member->sponsor_id = 7;
    $member->save();
    echo "Fixed member 4: sponsor_id set to 7\n";
} else {
    echo "Member 4 not found\n";
}

// Show all members
$members = App\Models\Member::select('id', 'member_id', 'full_name', 'sponsor_id', 'leg', 'placement_path', 'depth')
    ->orderBy('id')
    ->get();

echo "\nAll members:\n";
foreach ($members as $m) {
    echo sprintf(
        "ID: %d, MemberID: %s, Name: %s, Sponsor: %s, Leg: %s, Path: %s, Depth: %d\n",
        $m->id,
        $m->member_id,
        $m->full_name,
        $m->sponsor_id ?? 'NULL',
        $m->leg ?? 'NULL',
        $m->placement_path,
        $m->depth
    );
}
