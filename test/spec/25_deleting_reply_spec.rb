feature "Deleting a reply" do
  given(:go_to_thread) { false }
  given(:captcha) { "GOODCAPTCHA" }
  given(:password) { "admin" }
  @last_seen_reply = nil

  background do
    visit "/corn/"
    first(".reply a.post-id").click if go_to_thread
    within first(".reply") do
      @last_seen_reply = find(".post-message").text
      click_button
    end
    fill_in "password", with: password
    fill_in "captcha_answer", with: captcha
    click_button "Delete"
  end

  context "when on a board page" do
    scenario "deletes the reply and redirects to the thread" do
      expect(page).to have_current_path(/\/corn\/t\/\d+/)
      expect(page).to_not have_content(@last_seen_reply)
    end
  end

  context "when on a thread page" do
    given(:go_to_thread) { true }
    scenario "deletes the reply and redirects to the thread" do
      expect(page).to have_current_path(/\/corn\/t\/\d+/)
      expect(page).to_not have_content(@last_seen_reply)
    end
  end

  context "when a bad password" do
    given(:password) { "wrongpassword" }
    scenario "stays on the delete page" do
      expect(page).to have_content("Unauthorized")
    end
  end

  context "when a bad captcha" do
    given(:captcha) { "BADCAPTCHA" }
    scenario "stays on the delete page" do
      expect(page).to have_content("Unauthorized")
    end
  end

  after do
    post_reply
  end
end
