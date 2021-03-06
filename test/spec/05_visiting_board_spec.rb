feature "Visiting a board" do
  background do
    visit "/index.php/corn/"
  end

  scenario "has the board name" do
    expect(page).to have_content("corn")
  end

  scenario "has a form for posting a new thread" do
    within("#new-post") do
      # Let board_new_spec handle the details
      expect(find("form")["action"]).to eq("#{Capybara.app_host}/index.php/corn/publish")
    end
  end
end
