feature "Visiting the root page" do
  scenario "lists links to the boards" do
    visit "/"
    expect(page).to have_link("corn")
    expect(page).to have_link("news")

    click_link "corn"
    expect(page).to have_current_path("/index.php/corn/")
  end
end
