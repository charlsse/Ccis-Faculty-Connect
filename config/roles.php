<?php

return [
    'admin' => ['label' => 'Administrator / President', 'permissions' => ['accounts.manage', 'directory.view', 'payments.view', 'announcements.manage']],
    'secretary' => ['label' => 'Secretary', 'permissions' => ['directory.view', 'announcements.manage']],
    'treasurer' => ['label' => 'Treasurer', 'permissions' => ['payments.view', 'payments.manage']],
    'faculty' => ['label' => 'Faculty Member', 'permissions' => []],
    'staff' => ['label' => 'Staff (legacy)', 'permissions' => []],
];
