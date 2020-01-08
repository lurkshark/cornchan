feature "Visiting the root overboard" do
  background do
    visit "/"
  end

  scenario "shows recent threads and replies" do
    expect(page).to have_css(".thread")
  end
end
