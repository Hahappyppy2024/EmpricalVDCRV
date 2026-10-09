package importexport

import "context"

func ctxAsContext(c interface{ Done() <-chan struct{} }) context.Context {
	if v, ok := c.(context.Context); ok {
		return v
	}
	return context.Background()
}