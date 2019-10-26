feature "Visiting a board" do
  scenario "has the board name" do
    visit "/corn/"
    expect(page).to have_content("corn")
  end
end

feature "Posting a new thread to a board" do
  scenario "when the form is fully filled-out" do
    visit "/corn/"
    within("#newthread") do
      fill_in "lorem", with: "Dope new thread"
      fill_in "ipsum", with: "I have so many feelings"
      click_button "post"
    end

    expect(page).to have_current_path("/corn/")
    expect(page).to have_content("Dope new thread")
    expect(page).to have_content("I have so many feelings")
  end

  scenario "when the headline and post are omitted" do
    visit "/corn/"
    within("#newthread") do
      click_button "post"
    end

    expect(page).to have_current_path("/corn/")
    expect(page).to have_content("You need a headline")
  end

  scenario "when the headline is omitted" do
    visit "/corn/"
    within("#newthread") do
      click_button "post"
    end

    expect(page).to have_current_path("/corn/")
    expect(page).to have_content("You need a headline")
  end

  scenario "when the post is omitted" do
    visit "/corn/"
    within("#newthread") do
      fill_in "lorem", with: "Tell me ur feelings"
      click_button "post"
    end

    expect(page).to have_current_path("/corn/")
    expect(page).to have_content("Tell me ur feelings")
  end
end
