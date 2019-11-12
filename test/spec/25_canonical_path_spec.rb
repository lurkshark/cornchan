feature "Trailing slash redirects" do
  scenario "board path without trailing slash to with" do
    visit "/corn"
    expect(page).to have_current_path("/corn/")
  end

  scenario "thread path with trailing slash to without" do
    visit "/corn/10000/"
    expect(page).to have_current_path("/corn/10000")
  end

  scenario "new thread path with trailing slash to without" do
    visit "/corn/new/"
    expect(page).to have_current_path("/corn/new")
  end
end
