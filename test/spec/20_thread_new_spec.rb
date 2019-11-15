feature "Posting a new reply to a thread" do
  given(:subject) { Array.new(5) { CORPUS.sample }.join(" ") }
  given(:message) { Array.new(64) { CORPUS.sample }.join(" ") }
  given(:captcha) { "TEST" }

  background do
    visit "/corn/10000/new"
  end

  context "when the form is fully filled-out" do
    background do
      within("#newreply") do
        fill_in "lorem", with: subject
        fill_in "ipsum", with: message
        fill_in "captcha-answer", with: captcha
        click_button "Submit"
      end
    end

    scenario "redirects to the thread and shows the new reply" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to have_content(subject)
      expect(page).to have_content(message)
    end
  end

  context "when the form is filled-out with a bad captcha" do
    background do
      within("#newreply") do
        fill_in "lorem", with: subject
        fill_in "ipsum", with: message
        fill_in "captcha-answer", with: "GARBAGE"
        click_button "Submit"
      end
    end

    scenario "stays on the new thread page and shows an error" do
      expect(page).to have_current_path("/corn/10000/new")
      expect(page).to have_content("You got the CAPTCHA wrong")
    end
  end

  context "when the form is empty" do
    background do
      within("#newreply") do
        click_button "Submit"
      end
    end

    scenario "stays on the new thread page and shows an error" do
      expect(page).to have_current_path("/corn/10000/new")
      expect(page).to have_content("You need a subject or message")
    end
  end

  context "when the subject is empty" do
    background do
      within("#newreply") do
        fill_in "ipsum", with: message
        fill_in "captcha-answer", with: captcha
        click_button "Submit"
      end
    end

    scenario "redirects to the thread and shows the new message-only post" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to have_content(message)
    end
  end

  context "when the message is empty" do
    background do
      within("#newreply") do
        fill_in "lorem", with: subject
        fill_in "captcha-answer", with: captcha
        click_button "Submit"
      end
    end

    scenario "redirects to the thread and shows the new subject-only post" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to have_content(subject)
    end
  end

  context "when the captcha cookie opt-in is checked" do
    background do
      within("#newreply") do
        fill_in "lorem", with: subject
        fill_in "captcha-answer", with: captcha
        check "opt-in-cookie"
        click_button "Submit"
      end
    end

    scenario "redirects to the board and doesn't prompt for a CAPTCHA" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to_not have_content("CAPTCHA")
    end
  end

  context "when the captcha cookie opt-in is unchecked" do
    background do
      within("#newreply") do
        fill_in "lorem", with: subject
        fill_in "captcha-answer", with: captcha
        uncheck "opt-in-cookie"
        click_button "Submit"
      end
    end

    scenario "redirects to the board and prompts for a CAPTCHA" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to have_content("CAPTCHA")
    end
  end
end
