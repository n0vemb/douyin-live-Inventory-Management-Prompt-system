package main

import (
	"encoding/base64"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
)

// mmToInch100 converts millimetres to hundredths of an inch (PaperSize unit).
func mmToInch100(mm int) int {
	return int(float64(mm)*100.0/25.4 + 0.5)
}

var tmpDir string

type PrintReq struct {
	Images     []string `json:"images"`
	Printer    string   `json:"printer"`
	PageWidth  int      `json:"pageWidth,omitempty"`
	PageHeight int      `json:"pageHeight,omitempty"`
}

type Resp struct {
	Success bool   `json:"success"`
	Message string `json:"message,omitempty"`
	Error   string `json:"error,omitempty"`
}

func init() {
	tmpDir = filepath.Join(os.TempDir(), "ppmart-print")
	os.MkdirAll(tmpDir, 0700)
}

func writeJSON(w http.ResponseWriter, v interface{}) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(v)
}

// psEscape escapes a string for use inside PowerShell single-quoted strings.
// In PowerShell single-quoted strings, a literal single quote is represented as ''.
func psEscape(s string) string {
	return strings.ReplaceAll(s, "'", "''")
}

// printImages prints each PNG via Windows GDI in a single PowerShell session.
// Each image gets its own PrintDocument (separate print job), correct for label printers.
func printImages(files []string, printerName string, pw, ph int) error {
	if len(files) == 0 {
		return fmt.Errorf("没有要打印的图片")
	}

	var sb strings.Builder
	sb.WriteString("Add-Type -AssemblyName System.Drawing\n")
	sb.WriteString("$errs = @()\n")

	for i, f := range files {
		absPath, _ := filepath.Abs(f)
		absPath = strings.ReplaceAll(absPath, "'", "''")

		sb.WriteString(fmt.Sprintf("$doc%d = New-Object System.Drawing.Printing.PrintDocument\n", i))
		sb.WriteString(fmt.Sprintf("$doc%d.DefaultPageSettings.Margins = New-Object System.Drawing.Printing.Margins(0,0,0,0)\n", i))
		if printerName != "" {
			sb.WriteString(fmt.Sprintf("$doc%d.PrinterSettings.PrinterName = '%s'\n", i, psEscape(printerName)))
		}
		if pw > 0 && ph > 0 {
			pwInch := mmToInch100(pw)
			phInch := mmToInch100(ph)
			sb.WriteString(fmt.Sprintf("$ps%d = New-Object System.Drawing.Printing.PaperSize('Label',%d,%d)\n", i, pwInch, phInch))
			sb.WriteString(fmt.Sprintf("$ps%d.RawKind = 256\n", i))
			sb.WriteString(fmt.Sprintf("$doc%d.DefaultPageSettings.PaperSize = $ps%d\n", i, i))
		}
		sb.WriteString(fmt.Sprintf("$img%d = [System.Drawing.Image]::FromFile('%s')\n", i, absPath))
		sb.WriteString(fmt.Sprintf("$doc%d.add_PrintPage({ param($s,$e)\n", i))
		sb.WriteString("\ttry {\n")
		sb.WriteString(fmt.Sprintf("\t\t$e.Graphics.DrawImage($img%d, 0, 0, $img%d.Width, $img%d.Height)\n", i, i, i))
		sb.WriteString("\t} catch {\n")
		sb.WriteString("\t\t$errs += $_.Exception.Message\n")
		sb.WriteString("\t}\n")
		sb.WriteString("\t$e.HasMorePages = $false\n")
		sb.WriteString("})\n")
		sb.WriteString(fmt.Sprintf("$doc%d.Print()\n", i))
		sb.WriteString(fmt.Sprintf("$img%d.Dispose()\n", i))
	}

	sb.WriteString(`
if ($errs.Count -gt 0) {
	Write-Error ($errs -join '; ')
	exit 1
}
`)
	cmd := exec.Command("powershell", "-NoProfile", "-NonInteractive", "-Command", sb.String())
	output, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("打印失败: %s\n%v", strings.TrimSpace(string(output)), err)
	}
	return nil
}

func handlePrint(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		writeJSON(w, Resp{Success: false, Error: "仅支持 POST"})
		return
	}

	var req PrintReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, Resp{Success: false, Error: "JSON 解析失败: " + err.Error()})
		return
	}

	if len(req.Images) == 0 {
		writeJSON(w, Resp{Success: false, Error: "缺少 images 字段"})
		return
	}

	var tmpFiles []string
	for i, b64 := range req.Images {
		data, err := base64.StdEncoding.DecodeString(b64)
		if err != nil {
			for _, f := range tmpFiles {
				os.Remove(f)
			}
			writeJSON(w, Resp{Success: false, Error: fmt.Sprintf("图片 %d base64 解码失败: %v", i+1, err)})
			return
		}
		tmpFile := filepath.Join(tmpDir, fmt.Sprintf("label_%d_%d.png", os.Getpid(), i))
		if err := os.WriteFile(tmpFile, data, 0644); err != nil {
			for _, f := range tmpFiles {
				os.Remove(f)
			}
			writeJSON(w, Resp{Success: false, Error: fmt.Sprintf("图片 %d 保存失败: %v", i+1, err)})
			return
		}
		tmpFiles = append(tmpFiles, tmpFile)
	}

	err := printImages(tmpFiles, req.Printer, req.PageWidth, req.PageHeight)

	for _, f := range tmpFiles {
		os.Remove(f)
	}

	if err != nil {
		writeJSON(w, Resp{Success: false, Error: err.Error()})
		return
	}

	writeJSON(w, Resp{Success: true, Message: fmt.Sprintf("已发送 %d 个标签到打印机", len(req.Images))})
}

func handlePing(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, map[string]interface{}{"success": true, "message": "pong"})
}

func handlePrinters(w http.ResponseWriter, r *http.Request) {
	cmd := exec.Command("powershell", "-NoProfile", "-Command",
		"Get-CimInstance Win32_Printer | Select-Object -ExpandProperty Name")
	output, err := cmd.Output()
	if err != nil {
		writeJSON(w, Resp{Success: false, Error: "获取打印机列表失败"})
		return
	}

	lines := strings.Split(strings.TrimSpace(string(output)), "\n")
	var list []string
	for _, line := range lines {
		if name := strings.TrimSpace(line); name != "" {
			list = append(list, name)
		}
	}
	if list == nil {
		list = []string{}
	}

	writeJSON(w, map[string]interface{}{
		"success":  true,
		"printers": list,
	})
}

func main() {
	port := "9188"
	if p := os.Getenv("PORT"); p != "" {
		port = p
	}

	http.HandleFunc("/print", handlePrint)
	http.HandleFunc("/ping", handlePing)
	http.HandleFunc("/printers", handlePrinters)

	log.Printf("PPMart Windows 打印代理启动于 :%s", port)
	if err := http.ListenAndServe(":"+port, nil); err != nil {
		log.Fatal(err)
	}
}
