feature "Visiting a thread" do
  background do
    # These tests depend on thread_new_spec
    visit "/corn/10000"
  end

  scenario "has the board and thread name" do
    expect(page).to have_content("/corn/10000")
  end

  scenario "has a form for posting a new thread" do
    within("#newreply") do
      # Let thread_new_spec handle the details
      expect(find("form")["action"]).to eq("#{Capybara.app_host}/corn/10000/new")
    end
  end
end
