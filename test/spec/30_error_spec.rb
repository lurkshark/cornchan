feature "Return error" do
  background do
    visit path
  end

  context "when a garbage path is requested" do
    given(:path) { "/blahz" }
    scenario "returns a 404" do
      expect(page).to have_content("Error 404")
    end
  end

  context "when a path for a non-existant board is requested" do
    given(:path) { "/notreal/" }
    scenario "returns a 404" do
      expect(page).to have_content("Error 404")
    end
  end

  context "when a path for a non-existant thread is requested" do
    given(:path) { "/corn/res/9999.html" }
    scenario "returns a 404" do
      expect(page).to have_content("Error 404")
    end
  end

  context "when a path for a mismatched thread is requested" do
    # This test depends on thread_new_spec
    # The correct board is actually /corn/
    given(:path) { "/news/res/1000.html" }
    scenario "returns a 404" do
      expect(page).to have_content("Error 404")
    end
  end
end
