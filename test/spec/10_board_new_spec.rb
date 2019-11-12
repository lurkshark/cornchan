feature "Posting a new thread to a board" do
  given(:subject) { Array.new(5) { CORPUS.sample }.join(" ") }
  given(:message) { Array.new(64) { CORPUS.sample }.join(" ") }

  background do
    visit "/corn/new"
  end

  context "when the form is fully filled-out" do
    background do
      within("#newthread") do
        fill_in "lorem", with: subject
        fill_in "ipsum", with: message
        click_button "Submit"
      end
    end

    scenario "redirects to the board and shows the new thread" do
      expect(page).to have_current_path("/corn/")
      expect(page).to have_content(subject)
      expect(page).to have_content(message)
    end
  end

  context "when the form is empty" do
    background do
      within("#newthread") do
        click_button "Submit"
      end
    end

    scenario "stays on the new thread page and shows an error" do
      expect(page).to have_current_path("/corn/new")
      expect(page).to have_content("You need a subject")
    end
  end

  context "when the subject is empty" do
    background do
      within("#newthread") do
        fill_in "ipsum", with: message
        click_button "Submit"
      end
    end

    scenario "stays on the new thread page and shows an error" do
      expect(page).to have_current_path("/corn/new")
      expect(page).to have_content("You need a subject")
    end
  end

  context "when the message is empty" do
    background do
      within("#newthread") do
        fill_in "lorem", with: subject
        click_button "Submit"
      end
    end

    scenario "redirects to the board and shows the new subject-only post" do
      expect(page).to have_current_path("/corn/")
      expect(page).to have_content(subject)
    end
  end
end
