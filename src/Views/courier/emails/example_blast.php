<p>Hi <?= esc($contact->first_name ?? 'there') ?>,</p>

<p>We wanted to share some exciting news with you. <?= esc($headline ?? 'Something great is coming your way.') ?></p>

<p>Here's what you need to know:</p>

<ul>
    <li>Item one from your campaign data</li>
    <li>Item two from your campaign data</li>
    <li>Item three from your campaign data</li>
</ul>

<p>
    <a href="https://yoursite.com/learn-more" style="display:inline-block;padding:12px 24px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">
        Learn More
    </a>
</p>

<p>Thanks for being with us,<br>The Team</p>
