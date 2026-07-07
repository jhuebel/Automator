<?php

namespace App\Enums;

enum ScriptLanguage: string
{
    case Bash = 'Bash';
    case PowerShell = 'PowerShell';
    case Python = 'Python';
    case Ansible = 'Ansible';
    case Terraform = 'Terraform';

    public function label(): string
    {
        return match ($this) {
            self::Bash => 'Bash',
            self::PowerShell => 'PowerShell',
            self::Python => 'Python',
            self::Ansible => 'Ansible Playbook',
            self::Terraform => 'Terraform',
        };
    }

    public function fileExtension(): string
    {
        return match ($this) {
            self::Bash => '.sh',
            self::PowerShell => '.ps1',
            self::Python => '.py',
            self::Ansible => '.yml',
            self::Terraform => '.tf',
        };
    }

    /**
     * Tailwind badge color classes for this language.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Bash => 'bg-green-100 text-green-800',
            self::PowerShell => 'bg-blue-100 text-blue-800',
            self::Python => 'bg-yellow-100 text-yellow-800',
            self::Ansible => 'bg-red-100 text-red-800',
            self::Terraform => 'bg-purple-100 text-purple-800',
        };
    }

    /**
     * CodeMirror language mode name used by the editor.js integration.
     */
    public function codeMirrorMode(): string
    {
        return match ($this) {
            self::Bash => 'shell',
            self::PowerShell => 'powershell',
            self::Python => 'python',
            self::Ansible => 'yaml',
            self::Terraform => 'hcl',
        };
    }

    /**
     * Command + args to execute a script file of this language. Terraform is
     * handled separately (init + apply in a working directory), not via a file.
     *
     * @return list<string>
     */
    public function commandFor(string $filePath): array
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        return match ($this) {
            self::Bash => $isWindows ? ['wsl.exe', 'bash', $filePath] : ['/bin/bash', $filePath],
            self::PowerShell => $isWindows
                ? ['powershell.exe', '-NonInteractive', '-File', $filePath]
                : ['pwsh', '-NonInteractive', '-File', $filePath],
            self::Python => $isWindows ? ['python.exe', $filePath] : ['python3', $filePath],
            self::Ansible => ['ansible-playbook', $filePath],
            self::Terraform => throw new \LogicException('Terraform uses a dedicated execution path.'),
        };
    }
}
