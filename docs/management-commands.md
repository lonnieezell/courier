# Management Commands

Courier's management commands give you a CLI interface for creating and managing data — contacts, campaigns, tags, segments, and drip enrollments — without touching the database directly or writing a controller.

Every command follows the same pattern: `php spark courier:<resource> <action> [--options]`. If you omit a required option, the command prompts you interactively. Pass `--force` to skip confirmation prompts in scripts.

These commands are for human and deployment workflows. For the automated cron commands that send emails and process drips, see [CLI Commands](commands.md).

---

## `courier:contacts`

```bash
php spark courier:contacts <action> [options]
```

| Action | What it does |
|--------|-------------|
| `list` | Table of contacts, filterable by status or tag |
| `show` | Full details for one contact by email |
| `subscribe` | Add a new contact and optionally apply tags |
| `unsubscribe` | Unsubscribe a contact (prompts for confirmation) |
| `tag` | Apply one or more tags to a contact |
| `untag` | Remove one or more tags from a contact |

**Examples:**

```bash
# List the 20 most recent contacts
php spark courier:contacts list

# Filter by status or tag
php spark courier:contacts list --status=subscribed
php spark courier:contacts list --tag=early-access --limit=50

# Look up a contact
php spark courier:contacts show --email=jane@example.com

# Subscribe a new contact with tags
php spark courier:contacts subscribe \
  --email=jane@example.com \
  --first-name=Jane \
  --last-name=Doe \
  --tags=beta-users,newsletter

# Unsubscribe (skips confirmation prompt)
php spark courier:contacts unsubscribe --email=jane@example.com --force

# Apply and remove tags
php spark courier:contacts tag --email=jane@example.com --tags=vip,newsletter
php spark courier:contacts untag --email=jane@example.com --tags=newsletter
```

---

## `courier:campaigns`

```bash
php spark courier:campaigns <action> [options]
```

| Action | What it does |
|--------|-------------|
| `list` | Table of campaigns, filterable by status |
| `show` | Full details for one campaign by ID |
| `create` | Create a new draft campaign |
| `schedule` | Set the send time for a campaign |
| `pause` | Pause a sending campaign |
| `resume` | Resume a paused campaign |
| `cancel` | Cancel a campaign |
| `stats` | Open rate, click rate, and counts for a campaign |

**Examples:**

```bash
# List all campaigns
php spark courier:campaigns list

# Filter by status
php spark courier:campaigns list --status=scheduled

# Show one campaign
php spark courier:campaigns show --id=42

# Create a draft (prompts for any missing options)
php spark courier:campaigns create \
  --name="May Newsletter" \
  --subject="What's new in May" \
  --from-name="Acme Team" \
  --from-email=news@acme.com \
  --view=emails/newsletter

# Schedule a campaign
php spark courier:campaigns schedule --id=42 --at="2026-06-01 09:00:00"

# Pause, resume, cancel
php spark courier:campaigns pause --id=42
php spark courier:campaigns resume --id=42
php spark courier:campaigns cancel --id=42 --force

# View send stats
php spark courier:campaigns stats --id=42
```

The `stats` output shows total recipients, sent, failed, opened, clicked, open rate, and click rate.

---

## `courier:drips`

```bash
php spark courier:drips <action> [options]
```

| Action | What it does |
|--------|-------------|
| `list` | Table of enrollments, filterable by email, campaign, or status |
| `enroll` | Enroll a contact in a drip campaign by name |
| `cancel` | Cancel a contact's enrollment |

**Examples:**

```bash
# List all active enrollments
php spark courier:drips list --status=active

# Filter by contact or campaign
php spark courier:drips list --email=jane@example.com
php spark courier:drips list --campaign="Welcome Sequence"

# Enroll a contact
php spark courier:drips enroll \
  --email=jane@example.com \
  --campaign="Welcome Sequence"

# Cancel an enrollment
php spark courier:drips cancel \
  --email=jane@example.com \
  --campaign="Welcome Sequence" \
  --force
```

If the contact is already enrolled in the named campaign, `enroll` exits with a warning rather than creating a duplicate.

---

## `courier:segments`

```bash
php spark courier:segments <action> [options]
```

| Action | What it does |
|--------|-------------|
| `list` | Table of all segments |
| `show` | Details and live contact count for one segment |
| `create` | Create a segment with filter rules |
| `delete` | Delete a segment |

**Examples:**

```bash
# List segments
php spark courier:segments list

# Show a segment and its current contact count
php spark courier:segments show --id=3

# Create interactively (prompts for each rule)
php spark courier:segments create --name="Pro users" --match-mode=all

# Create with rules as JSON (good for scripts)
php spark courier:segments create \
  --name="Pro users" \
  --match-mode=all \
  --rules='[{"field":"custom_fields->plan","op":"eq","value":"pro"}]'

# Delete
php spark courier:segments delete --id=3 --force
```

`show` runs `segmentService::previewCount()` live, so the contact count reflects the current state of your contacts table.

Supported operators for rules: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`.

---

## `courier:tags`

```bash
php spark courier:tags <action> [options]
```

| Action | What it does |
|--------|-------------|
| `list` | Table of all tags |
| `create` | Create a new tag |
| `delete` | Delete a tag |

**Examples:**

```bash
# List all tags
php spark courier:tags list

# Create a tag
php spark courier:tags create --slug=beta-users --label="Beta Users"

# Delete a tag
php spark courier:tags delete --slug=beta-users --force
```

!!! warning "Deleting a tag"
    Deleting a tag removes it from all contacts it's applied to. There's no cascade warning — use `--force` only when you're sure.

---

## Using `--force` in scripts

All destructive actions (unsubscribe, pause, cancel, delete) prompt for confirmation by default. Pass `--force` to skip the prompt when running these commands from a script or CI pipeline:

```bash
php spark courier:contacts unsubscribe --email=jane@example.com --force
php spark courier:campaigns cancel --id=42 --force
php spark courier:tags delete --slug=old-tag --force
```
