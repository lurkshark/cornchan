feature "Visiting a thread" do
  background do
    # These tests depend on thread_new_spec
    visit "/corn/t/1000"
  end

  scenario "has the board and thread name" do
    expect(page).to have_content("corn")
    expect(page).to have_content("1000")
  end

  scenario "has a form for posting a new thread" do
    within("#new-post") do
      # Let thread_new_spec handle the details
      expect(find("form")["action"]).to eq("#{Capybara.app_host}/corn/t/1000/publish")
    end
  end
end
