feature "Deleting a thread" do
  given(:thread_with_replies) { false }
  given(:go_to_thread) { false }
  given(:captcha) { "GOODCAPTCHA" }
  given(:password) { "admin" }
  @last_seen_thread = nil
  @last_seen_reply = nil

  background do
    visit "/corn/"
    test_thread_id = find_all(".thread").filter do |thread|
      !thread_with_replies or thread.find_all(".reply").length > 0
    end.first[:id]
    visit "/corn/t/#{test_thread_id}" if go_to_thread
    within find_by_id(test_thread_id) do
      @last_seen_thread = first(".post-subject").text
      @last_seen_reply = thread_with_replies ?
        first(".reply .post-message").text : nil
      first("button").click
    end
    fill_in "password", with: password
    fill_in "captcha_answer", with: captcha
    click_button "Delete"
  end

  context "when on a board page" do
    scenario "deletes the thread and redirects to the board" do
      expect(page).to have_current_path("/corn/")
      expect(page).to_not have_content(@last_seen_thread)
    end
  end

  context "when on a thread page" do
    given(:go_to_thread) { true }
    scenario "deletes the thread and redirects to the board" do
      expect(page).to have_current_path("/corn/")
      expect(page).to_not have_content(@last_seen_thread)
    end
  end

  context "when thread has replies" do
    given(:thread_with_replies) { true }
    scenario "deletes the replies too" do
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
    post_thread
    post_reply(:newest)
  end
end
