feature "Posting a new thread to a board" do
  given(:headline) { Array.new(12) { Array('A'..'Z').sample }.join }
  given(:message) { Array.new(128) { Array('a'..'z').sample }.join }

  background do
    visit "/corn/new"
  end

  context "when the form is fully filled-out" do
    background do
      within("#newthread") do
        fill_in "lorem", with: headline
        fill_in "ipsum", with: message
        click_button "Submit"
      end
    end

    scenario "redirects to the board and shows the new thread" do
      expect(page).to have_current_path("/corn/")
      expect(page).to have_content(headline)
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
      expect(page).to have_content("You need a headline")
    end
  end

  context "when the headline is empty" do
    background do
      within("#newthread") do
        fill_in "ipsum", with: message
        click_button "Submit"
      end
    end

    scenario "stays on the new thread page and shows an error" do
      expect(page).to have_current_path("/corn/new")
      expect(page).to have_content("You need a headline")
    end
  end

  context "when the message is empty" do
    background do
      within("#newthread") do
        fill_in "lorem", with: headline
        click_button "Submit"
      end
    end

    scenario "redirects to the board and shows the new headline-only post" do
      expect(page).to have_current_path("/corn/")
      expect(page).to have_content(headline)
    end
  end
end
