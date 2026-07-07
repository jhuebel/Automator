# Usage

## Script Editor

The script editor is organised into three tabs:

- **General** — name, language, tags, and description
- **Code** — syntax-highlighted CodeMirror editor; AI Assistant is available here when an Anthropic API key is configured
- **Variables** — define input variables that are injected as environment variables at runtime

The editor fills the available viewport height. Save and Cancel are always visible at the bottom. Navigating away with unsaved changes triggers a confirmation dialog.

## Script Variables

Variables are defined on the **Variables** tab and injected into the script process as environment variables at runtime.

### Variable Types

| Type | Description | Accessing in script |
|---|---|---|
| **Text** | Any string value | `$VAR` / `$env:VAR` / `os.environ['VAR']` / `var.VAR` (Terraform) |
| **Number** | Numeric value; validated before run | same as Text — arrives as a string |
| **Boolean** | True/false checkbox | arrives as the string `true` or `false` |
| **List** | Comma-separated list of values | parse with `IFS`, `-split`, or `.split(',')` |

### Accessing Variables

**Bash**
```bash
echo "$MY_VAR"

# Array example
IFS=',' read -ra ITEMS <<< "$MY_ARRAY"
for item in "${ITEMS[@]}"; do echo "$item"; done
```

**PowerShell**
```powershell
Write-Host $env:MY_VAR

# Array example
$items = $env:MY_ARRAY -split ','
$items | ForEach-Object { Write-Host $_ }
```

**Python**
```python
import os
value = os.environ['MY_VAR']

# Array example
items = os.environ['MY_ARRAY'].split(',')
for item in items:
    print(item.strip())
```

### Required Variables

Mark a variable as **Required** to prevent the script from running until a value is provided. The Run button is disabled until all required variables are filled and all Number variables contain valid numeric values.

### Default Values

Set a default value on the **Variables** tab in the editor. The runner pre-fills the input with this value; the user can override it before each run. Scheduled jobs always use the default values.

## Terraform Scripts

Terraform scripts are written as a single HCL file (`main.tf`). When you run a Terraform script, Automator:

1. Creates a temporary working directory
2. Writes your script content as `main.tf`
3. Runs `terraform init`
4. If init succeeds, runs `terraform apply -auto-approve`
5. Streams the output of both phases in real time
6. Deletes the working directory when finished

### Variables

Variables are automatically exposed to Terraform via the `TF_VAR_` prefix convention. A variable named `region` in Automator is available inside your HCL as `var.region` — no manual prefixing required.

```hcl
variable "region" {}

provider "aws" {
  region = var.region
}
```

### State

The working directory is deleted after every run, so Terraform state is ephemeral. For persistent state, configure a [remote backend](https://developer.hashicorp.com/terraform/language/backend) (such as S3 or Terraform Cloud) inside your HCL:

```hcl
terraform {
  backend "s3" {
    bucket = "my-tf-state"
    key    = "automator/terraform.tfstate"
    region = "us-east-1"
  }
}
```

### Requirements

`terraform` must be installed and available on the system PATH. Check **Settings → System Status** to verify it is detected.

## Script Runner

1. Select a script from the list on the left.
2. Fill in any variable values (pre-populated with defaults).
3. Click **Run**.

Output streams line-by-line in real time. Click **Cancel** to terminate the process early. All runs — successful or not — are saved to **History**.

Exit code `0` = success; any other value = failure.

Scripts run as the OS user hosting the application and inherit its environment variables.

## Job Scheduler

Scheduled jobs run scripts automatically on a cron schedule. A scheduler tick runs once a minute, so jobs fire within about 60 seconds of their exact due time.

- **Enable / Disable** — toggle a job without deleting it.
- **Run Now** — trigger a job immediately outside its schedule.
- Jobs that are still running when their next fire time arrives are skipped to avoid overlapping executions.
- Scheduled jobs use each script's variable default values.

### Cron Syntax

Standard 5-field cron: `minute  hour  day-of-month  month  day-of-week`

| Expression | Schedule |
|---|---|
| `* * * * *` | Every minute |
| `*/5 * * * *` | Every 5 minutes |
| `0 * * * *` | Every hour, on the hour |
| `0 8 * * *` | Daily at 08:00 |
| `0 8 * * 1-5` | Weekdays at 08:00 |
| `0 0 * * 0` | Weekly — Sunday at midnight |
| `0 0 1 * *` | Monthly — 1st of the month at midnight |
| `30 6 1 1 *` | Yearly — 1 January at 06:30 |

## AI Assistant

The AI Assistant is available inside the **Code** tab of the script editor. It requires an Anthropic API key configured in **Settings → AI Assistant**.

| Mode | What it does |
|---|---|
| **Generate** | Writes a new script from a plain-language description. Existing content is replaced. |
| **Improve** | Rewrites the current script based on your instructions. |
| **Explain** | Summarises what the current script does in plain language. |

Output streams token-by-token. Click **Cancel** to stop mid-generation.

## Execution History

The History page shows every script execution. Click a row to expand the full output. The colored icon indicates status:

- Green check — exit code 0 (success)
- Red X — non-zero exit code (failure)
- Spinning indicator — currently running

## Help

- Click the **?** button in the top-right header to open the context-sensitive help drawer for the current page.
- Navigate to **/help** for the full documentation reference.
