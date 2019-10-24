feature "Root path page" do
  scenario "lists links to the boards" do
    visit "/"
    expect(page).to have_link("corn")
    expect(page).to have_link("prog")

    click_link "corn"
    expect(page).to have_current_path("/corn/")
  end
end
