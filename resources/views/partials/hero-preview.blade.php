<div class="fc-preview">
    <div class="fc-preview-top"><strong><span class="fc-monogram">fc</span> FacultyConnect</strong><span class="fc-tag">Illustrative preview</span></div>
    <div class="fc-preview-body">
        @if($visual === 'security')
            <span class="fc-metadata">ACCOUNT SECURITY</span><h3>Your account.<br>An extra layer of care.</h3>
            <div class="fc-security-demo"><span class="fc-shield" aria-hidden="true">✓</span><strong>Two-factor authentication</strong><small>A second step. Greater peace of mind.</small><div class="fc-code-demo" aria-hidden="true">• • • • • •</div></div>
            <div class="fc-preview-row"><span class="fc-small-icon">◎</span><div><strong>Trusted browsers</strong><small>Review and manage trusted access.</small></div></div>
            <div class="fc-preview-row"><span class="fc-small-icon">↗</span><div><strong>Role-based permissions</strong><small>The right tools for your role.</small></div></div>
        @else
            <span class="fc-metadata">{{ $visual === 'contributions' ? 'WATER CONTRIBUTIONS' : 'MY WORKSPACE' }}</span>
            <h3>{{ $visual === 'contributions' ? 'Every contribution.' : 'A little more connected.' }}<br>{{ $visual === 'contributions' ? 'A clearer record.' : 'A lot more organized.' }}</h3>
            <div class="fc-preview-balance"><span>Water contribution · Sample</span><div><strong>₱500<span>.00</span></strong><span class="fc-tag">✓ Verified</span></div><small>{{ $visual === 'contributions' ? 'Paid externally · Recorded by the Treasurer' : 'Recorded and verified by the Treasurer' }}</small></div>
            <div class="fc-preview-row"><span class="fc-small-icon">↗</span><div><strong>{{ $visual === 'contributions' ? 'Payment proof submitted' : 'Faculty announcements' }}</strong><small>{{ $visual === 'contributions' ? 'Reference and proof, kept together.' : 'Your community, on the same page.' }}</small></div></div>
            <div class="fc-preview-row"><span class="fc-small-icon">◎</span><div><strong>{{ $visual === 'contributions' ? 'History & private receipts' : 'My faculty profile' }}</strong><small>{{ $visual === 'contributions' ? 'A record you can come back to.' : 'Your details. One secure place.' }}</small></div></div>
        @endif
    </div>
</div>
