.PHONY: zip clean

PLUGIN_NAME = instagram-scraper
ZIP_FILE = $(PLUGIN_NAME).zip

# Build a WordPress-installable zip from the wordpress/ directory
zip: clean
	@echo "Building $(ZIP_FILE)..."
	@cd wordpress && zip -r ../$(ZIP_FILE) . -x '*.DS_Store'
	@echo "Done: $(ZIP_FILE) (upload via WordPress admin > Plugins > Add New > Upload Plugin)"

clean:
	@rm -f $(ZIP_FILE)
