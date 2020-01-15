feature "Visiting the root overboard" do
  background do
    visit "/"
  end

  context "when posting a new thread" do
    scenario "shows the new thread at the top" do
      new_thread_subject = post_thread
      visit "/"
      expect(first(".thread .post-subject")).to have_text(new_thread_subject)
    end
  end

  context "when posting a new reply" do
    scenario "shows the replied-to thread at the top" do
      bumped_thread_subject = post_reply(:oldest)
      visit "/"
      expect(first(".thread .post-subject")).to have_text(bumped_thread_subject)
    end
  end
end
