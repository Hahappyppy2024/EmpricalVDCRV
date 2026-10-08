package main

import (
	"bytes"
	"encoding/json"
	"flag"
	"fmt"
	"go/ast"
	"go/format"
	"go/parser"
	"go/token"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
)

type Branch struct {
	ID      int    `json:"id"`
	File    string `json:"file"`
	Line    int    `json:"line"`
	Kind    string `json:"kind"`
	Outcome string `json:"outcome"`
	Covered bool   `json:"covered"`
}

type Report struct {
	Covered   int      `json:"covered"`
	Total     int      `json:"total"`
	Percent   float64  `json:"percent"`
	Threshold float64  `json:"threshold"`
	Passed    bool     `json:"passed"`
	Branches  []Branch `json:"branches"`
}

type instrumenter struct {
	fset     *token.FileSet
	relFile  string
	nextID   int
	branches []Branch
}

func (i *instrumenter) add(pos token.Pos, kind, outcome string) int {
	id := i.nextID
	i.nextID++
	i.branches = append(i.branches, Branch{
		ID: id, File: i.relFile, Line: i.fset.Position(pos).Line,
		Kind: kind, Outcome: outcome,
	})
	return id
}

func intLit(v int) ast.Expr { return &ast.BasicLit{Kind: token.INT, Value: strconv.Itoa(v)} }
func hitStmt(id int) ast.Stmt {
	return &ast.ExprStmt{X: &ast.CallExpr{Fun: ast.NewIdent("__branchcovHit"), Args: []ast.Expr{intLit(id)}}}
}
func boolWrap(trueID, falseID int, expr ast.Expr) ast.Expr {
	return &ast.CallExpr{Fun: ast.NewIdent("__branchcovBool"), Args: []ast.Expr{intLit(trueID), intLit(falseID), expr}}
}

func (i *instrumenter) instrumentFile(f *ast.File) {
	ast.Inspect(f, func(n ast.Node) bool {
		switch x := n.(type) {
		case *ast.IfStmt:
			t := i.add(x.If, "if", "true")
			f := i.add(x.If, "if", "false")
			x.Cond = boolWrap(t, f, x.Cond)
		case *ast.ForStmt:
			if x.Cond != nil {
				t := i.add(x.For, "for", "true")
				f := i.add(x.For, "for", "false")
				x.Cond = boolWrap(t, f, x.Cond)
			}
		case *ast.SwitchStmt:
			for _, stmt := range x.Body.List {
				cc, ok := stmt.(*ast.CaseClause)
				if !ok {
					continue
				}
				outcome := "case"
				if len(cc.List) == 0 {
					outcome = "default"
				}
				id := i.add(cc.Case, "switch", outcome)
				cc.Body = append([]ast.Stmt{hitStmt(id)}, cc.Body...)
			}
		case *ast.TypeSwitchStmt:
			for _, stmt := range x.Body.List {
				cc, ok := stmt.(*ast.CaseClause)
				if !ok {
					continue
				}
				outcome := "case"
				if len(cc.List) == 0 {
					outcome = "default"
				}
				id := i.add(cc.Case, "type-switch", outcome)
				cc.Body = append([]ast.Stmt{hitStmt(id)}, cc.Body...)
			}
		case *ast.SelectStmt:
			for _, stmt := range x.Body.List {
				cc, ok := stmt.(*ast.CommClause)
				if !ok {
					continue
				}
				outcome := "case"
				if cc.Comm == nil {
					outcome = "default"
				}
				id := i.add(cc.Case, "select", outcome)
				cc.Body = append([]ast.Stmt{hitStmt(id)}, cc.Body...)
			}
		}
		return true
	})
}

func copyTree(src, dst string) error {
	return filepath.WalkDir(src, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		rel, err := filepath.Rel(src, path)
		if err != nil {
			return err
		}
		if rel == "." {
			return os.MkdirAll(dst, 0755)
		}
		if d.IsDir() && (d.Name() == ".git" || d.Name() == "coverage" || strings.HasPrefix(d.Name(), ".branchcov-")) {
			return filepath.SkipDir
		}
		out := filepath.Join(dst, rel)
		if d.IsDir() {
			return os.MkdirAll(out, 0755)
		}
		info, err := d.Info()
		if err != nil {
			return err
		}
		data, err := os.ReadFile(path)
		if err != nil {
			return err
		}
		return os.WriteFile(out, data, info.Mode())
	})
}

func runtimeSource(pkg string) []byte {
	return []byte(fmt.Sprintf(`package %s

import (
    "fmt"
    "os"
    "sync"
)

var __branchcovMu sync.Mutex

func __branchcovHit(id int) {
    path := os.Getenv("BRANCH_COVERAGE_FILE")
    if path == "" { return }
    __branchcovMu.Lock()
    defer __branchcovMu.Unlock()
    f, err := os.OpenFile(path, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0600)
    if err != nil { return }
    _, _ = fmt.Fprintln(f, id)
    _ = f.Close()
}

func __branchcovBool(trueID, falseID int, value bool) bool {
    if value { __branchcovHit(trueID) } else { __branchcovHit(falseID) }
    return value
}
`, pkg))
}

func main() {
	target := flag.String("target", "internal/app", "business source directory to instrument")
	tests := flag.String("tests", "./tests/function", "go test package pattern")
	threshold := flag.Float64("threshold", 85, "minimum required branch coverage percentage")
	out := flag.String("out", "coverage/branch.json", "JSON report path")
	keep := flag.Bool("keep-work", false, "keep instrumented temporary project")
	flag.Parse()

	project, err := os.Getwd()
	if err != nil {
		panic(err)
	}
	tmp, err := os.MkdirTemp("", "go-branchcov-*")
	if err != nil {
		panic(err)
	}
	if !*keep {
		defer os.RemoveAll(tmp)
	} else {
		fmt.Printf("instrumented project: %s\n", tmp)
	}
	if err := copyTree(project, tmp); err != nil {
		panic(err)
	}

	targetDir := filepath.Join(tmp, filepath.FromSlash(*target))
	entries, err := os.ReadDir(targetDir)
	if err != nil {
		panic(err)
	}
	fset := token.NewFileSet()
	inst := &instrumenter{fset: fset, nextID: 1}
	pkgName := ""

	var names []string
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".go") || strings.HasSuffix(e.Name(), "_test.go") {
			continue
		}
		names = append(names, e.Name())
	}
	sort.Strings(names)
	for _, name := range names {
		path := filepath.Join(targetDir, name)
		parsed, err := parser.ParseFile(fset, path, nil, parser.ParseComments)
		if err != nil {
			panic(err)
		}
		if pkgName == "" {
			pkgName = parsed.Name.Name
		}
		rel, _ := filepath.Rel(tmp, path)
		inst.relFile = filepath.ToSlash(rel)
		inst.instrumentFile(parsed)
		var buf bytes.Buffer
		if err := format.Node(&buf, fset, parsed); err != nil {
			panic(err)
		}
		if err := os.WriteFile(path, buf.Bytes(), 0644); err != nil {
			panic(err)
		}
	}
	if pkgName == "" {
		panic("no Go source files found in target")
	}
	if err := os.WriteFile(filepath.Join(targetDir, "zz_branchcov_runtime.go"), runtimeSource(pkgName), 0644); err != nil {
		panic(err)
	}

	hitFile := filepath.Join(tmp, "branch-hits.txt")
	cmd := exec.Command("go", "test", *tests, "-count=1")
	cmd.Dir = tmp
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	cmd.Env = append(os.Environ(), "BRANCH_COVERAGE_FILE="+hitFile)
	testErr := cmd.Run()

	hit := map[int]bool{}
	if data, err := os.ReadFile(hitFile); err == nil {
		for _, line := range strings.Fields(string(data)) {
			if id, err := strconv.Atoi(line); err == nil {
				hit[id] = true
			}
		}
	}
	covered := 0
	for idx := range inst.branches {
		inst.branches[idx].Covered = hit[inst.branches[idx].ID]
		if inst.branches[idx].Covered {
			covered++
		}
	}
	total := len(inst.branches)
	pct := 100.0
	if total > 0 {
		pct = float64(covered) * 100 / float64(total)
	}
	report := Report{Covered: covered, Total: total, Percent: pct, Threshold: *threshold, Passed: pct >= *threshold && testErr == nil, Branches: inst.branches}
	data, _ := json.MarshalIndent(report, "", "  ")
	outPath := filepath.Join(project, filepath.FromSlash(*out))
	if err := os.MkdirAll(filepath.Dir(outPath), 0755); err != nil {
		panic(err)
	}
	if err := os.WriteFile(outPath, append(data, '\n'), 0644); err != nil {
		panic(err)
	}

	fmt.Printf("\nBranch coverage: %.2f%% (%d/%d outcomes)\n", pct, covered, total)
	fmt.Printf("Required branch threshold: %.2f%%\n", *threshold)
	if testErr != nil {
		fmt.Println("Result: FAIL (functional test run failed)")
		os.Exit(1)
	}
	if pct < *threshold {
		fmt.Printf("Result: FAIL (branch coverage %.2f%% < %.2f%%)\n", pct, *threshold)
		os.Exit(2)
	}
	fmt.Println("Result: PASS")
}
