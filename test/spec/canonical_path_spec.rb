feature "Trailing slash redirects" do
  scenario "board path without trailing slash to with" do
    visit "/corn"
    expect(page).to have_current_path("/corn/")
  end

  scenario "thread path with trailing slash to without" do
    visit "/corn/100/"
    expect(page).to have_current_path("/corn/100")
  end
end
