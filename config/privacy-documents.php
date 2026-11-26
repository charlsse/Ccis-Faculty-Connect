<?php

// Publication is a reviewed code release, not an administrator toggle.
return [
    'acknowledgment_enabled' => false,
    'required_reviews' => ['controller', 'contact', 'processing', 'retention', 'legal', 'owner'],
    'disclosure' => 'These documents reference recognized information security and privacy management standards for system development and planning. FacultyConnect does not currently hold ISO certification.',
    'documents' => [
        'privacy-notice' => ['title' => 'Privacy Notice', 'audience' => 'public', 'version' => '0.1-draft', 'status' => 'draft', 'file' => 'privacy-notice-v0.1.md', 'approval' => null, 'open_items' => ['Confirm the accountable controller and contact', 'Approve purposes, legal bases, recipients and retention']],
        'terms-of-use' => ['title' => 'Terms of Use', 'audience' => 'public', 'version' => '0.1-draft', 'status' => 'draft', 'file' => 'terms-of-use-v0.1.md', 'approval' => null, 'open_items' => ['Approve operator authority, acceptable use and dispute process']],
        'data-inventory' => ['title' => 'Data Inventory & Processing Register', 'audience' => 'internal', 'version' => '0.1-draft', 'status' => 'draft', 'file' => 'data-inventory-v0.1.md'],
        'verified-controls' => ['title' => 'Verified Controls & Review Gaps', 'audience' => 'internal', 'version' => '0.1-draft', 'status' => 'draft', 'file' => 'verified-controls-v0.1.md'],
        'operational-policy' => ['title' => 'Proposed Retention, Access, Security & Disposal Policy', 'audience' => 'internal', 'version' => '0.1-draft', 'status' => 'draft', 'file' => 'operational-policy-v0.1.md'],
        'framework-references' => ['title' => 'Security & Privacy Framework References', 'audience' => 'internal', 'version' => '0.1-draft', 'status' => 'draft', 'file' => 'framework-references-v0.1.md'],
        'approval-register' => ['title' => 'Approval & Publication Register', 'audience' => 'internal', 'version' => '0.1-draft', 'status' => 'draft', 'file' => 'approval-register-v0.1.md'],
    ],
];
