feature "Return error" do
  scenario "404 on unknown garbage path" do
    visit "/blahz"
    expect(page).to have_content("Error 404")
  end
end
