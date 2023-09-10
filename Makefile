.PHONY: changelog
changelog:
	git log $$(git describe --abbrev=0 --tags)...HEAD --no-merges --pretty=format:"* [%h](https://github.com/codelicia/trineforce/commit/%H) %s (%cN)"
