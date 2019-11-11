feature "Posting a new reply to a thread" do
  given(:subject) { Array.new(5) { CORPUS.sample }.join(" ") }
  given(:message) { Array.new(64) { CORPUS.sample }.join(" ") }

  background do
    visit "/corn/10001/new"
  end

  context "when the form is fully filled-out" do
    background do
      within("#newreply") do
        fill_in "lorem", with: subject
        fill_in "ipsum", with: message
        click_button "Submit"
      end
    end

    scenario "redirects to the thread and shows the new reply" do
      expect(page).to have_current_path("/corn/10001")
      expect(page).to have_content(subject)
      expect(page).to have_content(message)
    end
  end

  context "when the form is empty" do
    background do
      within("#newreply") do
        click_button "Submit"
      end
    end

    scenario "stays on the new thread page and shows an error" do
      expect(page).to have_current_path("/corn/10001/new")
      expect(page).to have_content("You need a subject or message")
    end
  end

  context "when the subject is empty" do
    background do
      within("#newreply") do
        fill_in "ipsum", with: message
        click_button "Submit"
      end
    end

    scenario "redirects to the thread and shows the new message-only post" do
      expect(page).to have_current_path("/corn/10001")
      expect(page).to have_content(message)
    end
  end

  context "when the message is empty" do
    background do
      within("#newreply") do
        fill_in "lorem", with: subject
        click_button "Submit"
      end
    end

    scenario "redirects to the thread and shows the new subject-only post" do
      expect(page).to have_current_path("/corn/10001")
      expect(page).to have_content(subject)
    end
  end
end
