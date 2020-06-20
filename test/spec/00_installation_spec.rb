feature "Installation page" do
  background do
    visit "/"
  end

  scenario "shows preconditions" do
    expect(page).to have_content("Files can be written and deleted on your server")
    expect(page).to have_content("GD PHP extension is installed and supports the required filetypes")
    expect(page).to have_content("DBA PHP extension is installed and supports an acceptable handler")
  end

  scenario "takes initial configuration" do
    fill_in "admin_password", with: "admin"
    # Only filling-in fields that need
    # to be changed for tests
    click_button "Install"

    expect(page).to have_current_path("/index.php")
    expect(page).to_not have_content("installation")
  end
end

