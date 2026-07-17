package main

// Version identifies this runner build. Bump it when making a change worth
// being able to tell apart in the fleet — e.g. the PATH-resolution fix in
// runtimes.go/executor.go — so the management plane can flag runners still
// running older code (Settings > Runners) instead of that being invisible.
const Version = "1.3.0"
