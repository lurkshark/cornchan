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

  context "when a badly arranged path is requested" do
    given(:path) { "/1000/new" }
    scenario "returns a 404" do
      expect(page).to have_content("Error 404")
    end
  end
end
