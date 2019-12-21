feature "Visiting a thread" do
  given(:thread_id) do
    page.current_path.match(/\/corn\/t\/(\d+)/)[1]
  end

  background do
    visit "/corn/"
    first(".thread a.post-id").click
  end

  scenario "has the board and thread id" do
    expect(page).to have_content("corn")
    expect(page).to have_content(thread_id)
  end

  scenario "has a form for posting a new thread" do
    within("#new-post") do
      # Let thread_new_spec handle the details
      expect(find("form")["action"]).to eq("#{Capybara.app_host}/corn/t/#{thread_id}/publish")
    end
  end
end
