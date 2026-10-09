package repo

import (
	"encoding/json"
)

func decodeSteps(s string) []map[string]any {
	if s == "" {
		return []map[string]any{}
	}
	var raw []map[string]any
	if err := json.Unmarshal([]byte(s), &raw); err != nil {
		return []map[string]any{}
	}
	return raw
}

func decodeMap(s string) map[string]string {
	if s == "" {
		return map[string]string{}
	}
	m := map[string]string{}
	_ = json.Unmarshal([]byte(s), &m)
	return m
}

func decodeAnyMap(s string) map[string]any {
	if s == "" {
		return map[string]any{}
	}
	m := map[string]any{}
	_ = json.Unmarshal([]byte(s), &m)
	return m
}