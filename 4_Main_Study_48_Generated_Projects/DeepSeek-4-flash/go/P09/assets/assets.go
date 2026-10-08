// Package assets embeds the HTML templates, CSS and JavaScript served to the
// browser client.
package assets

import "embed"

//go:embed templates static
var Files embed.FS
