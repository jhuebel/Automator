package main

import (
	"fmt"
	"runtime"
)

// commandFor mirrors App\Enums\ScriptLanguage::commandFor() on the Laravel
// side — the runner decides its own command using its own runtime.GOOS,
// since Laravel no longer executes scripts itself.
func commandFor(language, filePath string) ([]string, error) {
	isWindows := runtime.GOOS == "windows"

	switch language {
	case "Bash":
		if isWindows {
			return []string{"wsl.exe", "bash", filePath}, nil
		}
		return []string{"/bin/bash", filePath}, nil
	case "PowerShell":
		if isWindows {
			return []string{"powershell.exe", "-NonInteractive", "-File", filePath}, nil
		}
		return []string{"pwsh", "-NonInteractive", "-File", filePath}, nil
	case "Python":
		if isWindows {
			return []string{"python.exe", filePath}, nil
		}
		return []string{"python3", filePath}, nil
	case "Ansible":
		return []string{"ansible-playbook", filePath}, nil
	default:
		return nil, fmt.Errorf("unsupported language for direct execution: %s", language)
	}
}

func fileExtension(language string) string {
	switch language {
	case "Bash":
		return ".sh"
	case "PowerShell":
		return ".ps1"
	case "Python":
		return ".py"
	case "Ansible":
		return ".yml"
	case "Terraform":
		return ".tf"
	default:
		return ".txt"
	}
}
